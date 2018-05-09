<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午2:32
 */

namespace yii\swoole\moniter;


use Swoole\Coroutine\Channel;
use Yii;
use yii\swoole\moniter\Parser\ParserInterface;
use yii\swoole\moniter\Reader\ReaderInterface;
use yii\swoole\moniter\Writer\WriterInterface;
use yii\swoole\process\BaseProcess;

class MonitProcess extends BaseProcess
{

    /**
     * @var ReaderInterface
     */
    public $reader;

    /**
     * @var WriterInterface
     */
    public $writer;

    /**
     * @var ParserInterface
     */
    public $parser;

    public $readChannel;

    public $writeChannel;

    public $state = 1;

    const RUNNING = 1;
    const STOP = 0;

    public function init()
    {
        parent::init();
        if (!$this->reader instanceof ReaderInterface) {
            $this->reader = Yii::createObject($this->reader);
        }
        if (!$this->writer instanceof WriterInterface) {
            $this->writer = Yii::createObject($this->writer);
        }
        if (!$this->parser instanceof ParserInterface) {
            $this->parser = Yii::createObject($this->parser);
        }
    }

    private function readerStart()
    {
        $this->reader->open();
        for ($i = 0; $i < $this->reader->goer; $i++) {
            go(function () {
                while ($this->state !== self::STOP) {
                    $content = $this->reader->read();
                    if (!empty($content)) {
                        $this->readChannel->push($content);
                    }
                }
                $this->readChannel->push(self::STOP);
            });
        }
    }

    private function parserStart()
    {
        for ($i = 0; $i < $this->parser->goer; $i++) {
            go(function () {
                while (true) {
                    $content = $this->readChannel->pop();
                    if ($content === self::STOP) {
                        break;
                    }
                    $content = $this->parser->parse($content);
                    $this->writeChannel->push($content);
                }
                $this->readChannel->close();
                $this->reader->close();
                $this->writeChannel->push(self::STOP);
            });
        }
    }

    private function writerStart()
    {
        $this->writer->open();
        for ($i = 0; $i < $this->writer->goer; $i++) {
            go(function () {
                while (true) {
                    $content = $this->writeChannel->pop();
                    if ($content === self::STOP) {
                        break;
                    }
                    $this->writer->write($content);
                }
                $this->writeChannel->close();
                $this->writer->close();
            });
        }
    }

    public function close()
    {
        $this->state = self::STOP;
    }

    public function start($class, $config)
    {
        $process = new \swoole_process(function ($process) {
            $process->name('swoole-moniter');
            $this->readChannel = new Channel($this->reader->capacity);

            $this->writeChannel = new Channel($this->writer->capacity);

            $this->readerStart();

            $this->parserStart();

            $this->writerStart();
        }, false, 2);

        if ($this->server) {
            $this->server->addProcess($process);
        } else {
            $pid = $process->start();
            if (!in_array($pid, $this->pids)) {
                $this->pids[] = $pid;
            }
            if (!isset($this->processArray[$pid])) {
                $this->processArray[$pid] = $process;
            }
            $this->savePid();
        }
    }
}
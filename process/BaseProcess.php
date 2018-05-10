<?php

namespace yii\swoole\process;

use Yii;
use yii\swoole\Application;
use yii\swoole\files\FileIO;
use yii\swoole\server\WorkTrait;

abstract class BaseProcess extends \yii\base\Component
{
    public $processArray = [];
    public $processList = [];
    public $pidFile;
    public $pids = [];
    public $memory = 512;
    protected $config = [];
    protected $root;
    protected $server = null;
    public $log_path = '/data/logs';
    public $boot = false;
    public $name;
    public $num = 1;
    public $inout = 0;
    public $pipe = 0;

    public function init()
    {
        parent::init();
        $this->server = Yii::$app->getSwooleServer();
        if (!$this->pidFile) {
            $this->pidFile = sys_get_temp_dir() . '/' . $this->name . '.pid';
        }
    }

    public function startAll()
    {
        if ($this->processList) {
            $this->start();
        } else {
            $p = new \swoole_process(function ($process) {
                $process->name('swoole-' . $this->name);
                $this->start();
            }, $this->inout, $this->pipe);
            $this->saveProcess($p);
        }
    }

    abstract public function start();

    public function savePid()
    {
        if ($this->pidFile && $this->pids) {
            go(function () {
                FileIO::write($this->pidFile, implode(',', $this->pids));
            });

        }
    }

    public function saveProcess($p)
    {
        if ($this->server) {
            $this->server->addProcess($p);
        } else {
            $pid = $p->start();
            if (!in_array($pid, $this->pids)) {
                $this->pids[] = $pid;
            }
            if (!isset($this->processArray[$pid])) {
                $this->processArray[$pid] = $p;
            }
        }
    }

    public function stop($pid, $status = 0)
    {
        $process = $this->processArray[$pid];
        $process->exit($status);
        unset($this->processArray[$pid]);
    }

    public function stopAll($status = 0)
    {
        go(function () use ($status) {
            try {
                foreach ($this->processArray as $pid => $process) {
                    $process->exit($status);
                }
                $content = FileIO::read($this->pidFile);
                if (is_string($content)) {
                    $pids = explode(',', $content);
                }
                if (is_array($pids)) {
                    foreach ($pids as $pid) {
                        \swoole_process::kill(intval($pid));
                    }
                }
            } catch (\Exception $e) {
                print_r($e);
            } finally {
                @unlink($this->pidFile);
            }
        });
    }

    /**
     * 判断内存使用是否超出
     * @param  int $memoryLimit
     * @return bool
     */
    public function memoryExceeded($pid, $name)
    {
        if ((memory_get_usage() / 1024 / 1024) >= $this->memory) {
            FileIO::write($this->log_path . '/' . $name . '.log', 'out of memory!', FILE_APPEND);
            $this->release();
            $this->stop($pid);
        }
    }

    public function release()
    {
        if (!$this->server) {
            Yii::getLogger()->flush(true);
            Yii::$app->clearComponents();
        }
    }

}

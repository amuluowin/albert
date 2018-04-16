<?php

namespace yii\swoole\process;

use Yii;
use yii\swoole\files\FileIO;
use yii\swoole\server\WorkTrait;

abstract class BaseProcess extends \yii\base\Component
{
    use WorkTrait;
    public $processArray = [];
    public $pidFile;
    public $pids = [];
    public $processList = [];
    public $memory = 512;
    protected $config = [];
    protected $root;
    protected $server = null;
    public $log_path = '/data/logs';

    public function init()
    {
        parent::init();
        $this->server = Yii::$app->getSwooleServer();
    }

    public function startAll($workConfig)
    {
        $this->config = $workConfig;
        $this->root = $workConfig['root'];
        if ($this->processList) {
            foreach ($this->processList as $class => $config) {
                $this->start($class, $config);
            }
        } else {
            $this->start(null, null);
        }
    }

    abstract public function start($class, $config);

    public function savePid()
    {
        if ($this->pidFile) {
            FileIO::write($this->pidFile, implode(',', $this->pids), FILE_APPEND);
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
        foreach ($this->processArray as $pid => $process) {
            $process->exit($status);
        }
        $content = FileIO::read($this->pidFile);
        if (is_string($content)) {
            $pids = explode(',', $content);
        }
        foreach ($pids as $pid) {
            \swoole_process::kill(intval($pid));
        }
        @unlink($this->pidFile);
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

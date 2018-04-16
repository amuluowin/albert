<?php

namespace yii\swoole\process;

use Yii;
use yii\swoole\files\FileIO;

class BackendProcess extends BaseProcess
{
    public function start($class, $config)
    {
        if ($class && $config) {
            $config['class'] = $class;
            $obj = Yii::createObject($config);
            $baseprocess = new \swoole_process(function ($process) use ($obj) {
                $this->workerStart();
                $process->name('swoole-backend-' . $obj->processName);
                $process->retry = 0;
                swoole_timer_tick($obj->ticket * 1000, function () use ($process, $obj) {
                    if ($obj->use_coro) {
                        \Swoole\Coroutine::Create(function () use ($process, $obj) {
                            $this->doWork($process, $obj);
                        });
                    } else {
                        $this->doWork($process, $obj);
                    }
                });
            }, false, 2);

            if ($this->server) {
                $this->server->addProcess($baseprocess);
            } else {
                $pid = $baseprocess->start();
                if (!in_array($pid, $this->pids)) {
                    $this->pids[] = $pid;
                }
                if (!isset($this->processArray[$pid])) {
                    $this->processArray[$pid] = $baseprocess;
                }
                $this->savePid();
            }
        }
    }

    private function doWork($process, $obj)
    {
        try {
            $obj->doWork();
            $this->memoryExceeded($process->pid, $obj->processName);
            $this->release();
        } catch (\Exception $e) {
            FileIO::write($this->log_path . '/' . $obj->processName . '.log', (string)$e, FILE_APPEND);
            $this->release();
            if ($obj->retry <= $process->retry) {
                $this->stop($process->pid);
            }
            $process->retry++;
        }
    }
}

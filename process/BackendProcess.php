<?php

namespace yii\swoole\process;

use Yii;

class BackendProcess extends BaseProcess
{
    public function start()
    {
        foreach ($this->processList as $class => $config) {
            $this->create($class, $config);
        }
    }

    private function create($class, $config)
    {
        if ($class && $config) {
            $config['class'] = $class;
            $obj = Yii::createObject($config);
            $baseprocess = new \swoole_process(function ($process) use ($obj) {
                $process->name('swoole-' . $this->name . '-' . $obj->processName);
                $process->retry = 0;
                swoole_timer_tick($obj->ticket * 1000, function () use ($process, $obj) {
                    \Swoole\Runtime::enableCoroutine();
                    if ($obj->use_coro) {
                        \Swoole\Coroutine::Create(function () use ($process, $obj) {
                            $this->doWork($process, $obj);
                        });
                    } else {
                        $this->doWork($process, $obj);
                    }
                });
            }, $this->inout, $this->pipe);

            $this->saveProcess($baseprocess);
            $this->savePid();
        }
    }

    private function doWork($process, $obj)
    {
        try {
            $obj->doWork();
            $this->memoryExceeded($process->pid, $obj->processName);
            $this->release();
        } catch (\Exception $e) {
            file_put_contents($this->log_path . '/' . $obj->processName . '.log', (string)$e, FILE_APPEND);
            $this->release();
            if ($obj->retry <= $process->retry) {
                $this->stop($process->pid);
            }
            $process->retry++;
        }
    }
}

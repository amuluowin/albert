<?php

namespace yii\swoole\server;

use Yii;
use yii\swoole\base\SingletonTrait;
use yii\swoole\process\IProcessServer;

class ProcessServer implements IProcessServer
{
    use SingletonTrait;

    public function start($work)
    {
        if ($work) {
            $work->startAll();
        }
    }

    public function stop($work)
    {
        $work->stopAll();
    }
}

<?php

namespace yii\swoole\server;

use Yii;
use yii\swoole\process\IProcessServer;

class ProcessServer implements IProcessServer
{
    public static $instance;

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

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new ProcessServer();
        }
        return self::$instance;
    }

}

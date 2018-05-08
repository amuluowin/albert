<?php

namespace yii\swoole\server;

use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\process\IProcessServer;

class ProcessServer implements IProcessServer
{
    public static $instance;

    private $works = [];

    public function start($config, $work)
    {
        if ($work) {
            $work->startAll(ArrayHelper::getValue($config, 'common'));
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

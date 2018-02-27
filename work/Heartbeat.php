<?php

namespace yii\swoole\work;

use Yii;
use yii\swoole\server\WorkTrait;

class Heartbeat
{
    use WorkTrait;

    private static $instance;

    private $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    public function Moint()
    {
        //服务监控
        $reload_process = new \swoole_process(function ($process) {
            $process->name('SWD-MOINT');
            new SrvHeartbeat();
        }, false, 2);
        Yii::$app->getSwooleServer()->addProcess($reload_process);
    }

    public function UpSrv()
    {
        //服务上报
        $reload_process = new \swoole_process(function ($process) {
            $process->name('SWD-SRVUP');
            new SrvUpHeartbeat();
        }, false, 2);
        Yii::$app->getSwooleServer()->addProcess($reload_process);
    }

    public function Inotity($path)
    {
        $reload_process = new \swoole_process(function ($process) use ($path) {
            $process->name('SWD-RELOAD');
            new \yii\swoole\work\InotifyProcess(Yii::$app->getSwooleServer(), $path);
        }, false, 2);
        Yii::$app->getSwooleServer()->addProcess($reload_process);
    }

    public static function getInstance($config)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

}

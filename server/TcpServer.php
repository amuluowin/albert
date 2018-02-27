<?php

namespace yii\swoole\server;

use swoole_server;
use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\tcp\TcpTrait;

class TcpServer extends Server
{
    use TcpTrait;
    public $server;
    public static $instance;

    public function __construct($config)
    {
        $this->allConfig = $config;
        $this->config = ArrayHelper::merge(ArrayHelper::getValue($config, 'tcp'), ArrayHelper::getValue($config, 'common'));
        $this->server = new swoole_server($this->config['host'], $this->config['port']);
        $this->name = $this->config['name'];
        Yii::$app->setSwooleServer($this->server);
        if (isset($this->config['pidFile'])) {
            $this->pidFile = $this->config['pidFile'];
        }
        $this->server->set($this->config['server']);

        $this->server->on('Receive', array($this, 'onReceive'));
        $this->server->on('Start', array($this, 'onStart'));
        $this->server->on('workerStart', array($this, 'onWorkerStart'));
        $this->server->on('managerStart', [$this, 'onManagerStart']);
        if (method_exists($this, 'onTask')) {
            $this->server->on('task', [$this, 'onTask']);
        }
        if (method_exists($this, 'onFinish')) {
            $this->server->on('finish', [$this, 'onFinish']);
        }
        $this->beforeStart();
        $this->server->start();
    }

    protected function beforeStart()
    {
        if (Yii::$app->params['Hearbeat']['UpSrv']) {
            \yii\swoole\work\Heartbeat::getInstance(Yii::$app->params['swoole'])->UpSrv();
        }
    }

    public static function getInstance($config)
    {
        if (!self::$instance) {
            self::$instance = new TcpServer($config);
        }
        return self::$instance;
    }

}

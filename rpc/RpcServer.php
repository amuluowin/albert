<?php

namespace yii\swoole\server;

use swoole_server;
use Yii;
use yii\swoole\helpers\ArrayHelper;

class RpcServer extends Server
{
    /**
     * @var swoole_server
     */
    public $server;

    /**
     * @var RpcServer
     */
    public static $instance;

    public function __construct($config)
    {
        $this->config = ArrayHelper::merge(ArrayHelper::getValue($config, 'tcp'), ArrayHelper::getValue($config, 'common'));
        $this->server = new swoole_server($this->config['host'], $this->config['port']);
        $this->name = $this->config['name'];
        Yii::$server = $this->server;
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

    public static function getInstance($config)
    {
        if (!self::$instance) {
            self::$instance = new RpcServer($config);
        }
        return self::$instance;
    }

}

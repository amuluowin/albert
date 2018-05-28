<?php

namespace yii\swoole\server;

use swoole_server;
use Yii;
use yii\swoole\base\SingletonTrait;
use yii\swoole\rpc\RpcTrait;

class RpcServer extends Server
{
    use SingletonTrait;
    use RpcTrait;
    /**
     * @var swoole_server
     */
    public $server;

    public function start()
    {
        $this->server = new swoole_server($this->config['host'], $this->config['port']);
        $this->name = $this->config['name'];
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
}

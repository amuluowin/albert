<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-24
 * Time: 下午3:08
 */

namespace yii\swoole\mqtt;


use yii\swoole\base\SingletonTrait;
use yii\swoole\server\Server;

class MqttServer extends Server
{
    use SingletonTrait;
    use MqttTrait;
    /**
     * @var swoole_server
     */
    public $server;

    public function start()
    {
        $this->server = new \Swoole\Server($this->config['host'], $this->config['port']);
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
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-23
 * Time: 下午3:59
 */

namespace yii\swoole\udp;

use Yii;
use yii\swoole\base\SingletonTrait;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\server\Server;

class UdpServer extends Server
{
    use UdpTrait;

    use SingletonTrait;

    public function start()
    {
        $this->server = new \Swoole\Server($this->config['host'], $this->config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
        $this->name = $this->config['name'];
        if (isset($this->config['pidFile'])) {
            $this->pidFile = $this->config['pidFile'];
        }
        $this->server->set($this->config['server']);

        $this->server->on('Receive', array($this, 'onReceive'));
        $this->server->on('Start', array($this, 'onStart'));
        $this->server->on('workerStart', array($this, 'onWorkerStart'));
        $this->server->on('managerStart', [$this, 'onManagerStart']);
        $this->server->on('Task', array($this, 'onTask'));
        $this->server->on('Finish', array($this, 'onFinish'));
        $this->beforeStart();
        $this->server->start();
    }
}
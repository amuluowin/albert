<?php

namespace yii\swoole\server;

use swoole_http_server;
use Yii;
use yii\swoole\base\BootInterface;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\helpers\SerializeHelper;
use yii\swoole\rpc\RpcTrait;
use yii\swoole\udp\UdpTrait;
use yii\swoole\web\HttpTrait;

/**
 * HTTP服务器
 *
 * @package yii\swoole\server
 */
class HttpServer extends Server
{
    use UdpTrait;
    use RpcTrait;
    use HttpTrait;

    /**
     * @var string 缺省文件名
     */
    public $indexFile = 'index.php';

    private static $instance;

    public function __construct($config)
    {
        $this->config = ArrayHelper::merge(ArrayHelper::getValue($config, 'web'), ArrayHelper::getValue($config, 'common'));
    }

    public function start()
    {
        $this->name = $this->config['name'];
        if (isset($this->config['debug'])) {
            $this->debug = $this->config['debug'];
        }
        if (isset($this->config['pidFile'])) {
            $this->pidFile = $this->config['pidFile'];
        }
        $this->createServer();
        $this->startServer();
    }

    protected function createServer()
    {
        $this->server = new swoole_http_server($this->config['host'], $this->config['port'], $this->config['type']);
    }

    protected function startServer()
    {

        $this->root = $this->config['root'];

        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('shutdown', [$this, 'onShutdown']);

        $this->server->on('managerStart', [$this, 'onManagerStart']);

        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('workerStop', [$this, 'onWorkerStop']);

        $this->server->on('request', [$this, 'onRequest']);

        if (method_exists($this, 'onOpen')) {
            $this->server->on('open', [$this, 'onOpen']);
        }
        if (method_exists($this, 'onClose')) {
            $this->server->on('close', [$this, 'onClose']);
        }

        if (method_exists($this, 'onHandShake')) {
            $this->server->on('handshake', [$this, 'onHandShake']);
        }
        if (method_exists($this, 'onMessage')) {
            $this->server->on('message', [$this, 'onMessage']);
        }

        if (method_exists($this, 'onTask')) {
            $this->server->on('task', [$this, 'onTask']);
        }
        if (method_exists($this, 'onFinish')) {
            $this->server->on('finish', [$this, 'onFinish']);
        }
        if (method_exists($this, 'onPipeMessage')) {
            $this->server->on('pipeMessage', [$this, 'onPipeMessage']);
        }

        $this->server->set($this->config['server']);
        $this->beforeStart();
        $this->server->start();
    }

    public function onPipeMessage($server, $from_worker_id, $message)
    {
        print_r(SerializeHelper::unserialize($message));
    }

    public static function getInstance($config)
    {
        if (!self::$instance) {
            self::$instance = new HttpServer($config);
        }
        return self::$instance;
    }

}

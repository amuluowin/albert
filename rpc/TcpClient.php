<?php

namespace yii\swoole\rpc;

use Swoole\Coroutine\Client;
use Yii;
use yii\swoole\governance\trace\TraceInterface;
use yii\swoole\pack\TcpPack;

class TcpClient extends IRpcClient
{
    /**
     * @var int
     */
    public $maxPoolSize = 10;

    /**
     * @var int
     */
    public $busy_pool = 5;
    /**
     * @var Client
     */
    public $client;

    /**
     * @var float
     */
    public $timeout = 0.5;

    /**
     * @var TraceInterface
     */
    public $tracer;

    const EVENT_BEFORE_SEND = 'beforeSend';
    const EVENT_AFTER_RECV = 'afterRecv';

    public function recv()
    {
        $result = TcpPack::decode($this->client->recv(), 'tcp');
        $this->trigger(self::EVENT_AFTER_RECV);
        Yii::$container->get('tcpclient')->recycle($this->client);
        return $result;
    }

    public function __call($name, $params)
    {
        list($service, $route) = $this->getService();
        $server = Yii::$app->gr->provider->getServices(Yii::$app->gr->provider->register['Name'], $service);
        list($server, $port) = array_shift($server);
        $key = 'corotcp:' . $server;
        if (!Yii::$container->hasSingleton('tcpclient')) {
            Yii::$container->setSingleton('tcpclient', [
                'class' => 'yii\swoole\pool\TcpPool'
            ]);
        }
        if (($this->client = Yii::$container->get('tcpclient')->fetch($key)) === null) {
            $this->client = Yii::$container->get('tcpclient')->create($key,
                [
                    'hostname' => $server,
                    'port' => $port,
                    'timeout' => $this->timeout,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])
                ->fetch($key);
        }

        $data = Yii::$app->gr->trace->getCollect(Yii::$app->request->getTraceId());
        list($data['service'], $data['route']) = $this->getService();
        $data['method'] = $name;
        $data['params'] = $params;
        $data['fastCall'] = $this->fastCall;
        Yii::$app->gr->trace->setCollect($data['traceId'], $data);
        $this->client->send(TcpPack::encode($data, 'tcp'));
        return $this;
    }
}

<?php

namespace yii\swoole\rpc;

use Swoole\Coroutine\Client;
use Yii;
use yii\swoole\pack\TcpPack;

class TcpClient implements IRpcClient
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

    public function recv()
    {
        $result = TcpPack::decode($this->client->recv(), 'tcp');
        Yii::$app->rpc->afterRecv($result);
        Yii::$container->get('tcpclient')->recycle($this->client);
        return $result;
    }

    public function __call($name, $params)
    {
        $data = [];
        list($data['service'], $data['route']) = Yii::$app->rpc->getService();
        $server = Yii::$app->gr->provider->getServices(Yii::$app->gr->provider->register['Name'], $data['service']);
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

        $data['method'] = $name;
        $data['params'] = array_shift($params);
        $data['fastCall'] = Yii::$app->rpc->fastCall;
        $data = Yii::$app->rpc->beforeSend($data);
        $this->client->send(TcpPack::encode($data, 'tcp'));
        return $this;
    }
}

<?php

namespace yii\swoole\rpc;

use Swoole\Coroutine\Client;
use Yii;
use yii\swoole\pack\TcpPack;

class TcpClient extends IRpcClient
{
    /**
     * @var int
     */
    public $maxPoolSize = 100;

    /**
     * @var int
     */
    public $busy_pool = 50;
    /**
     * @var Client
     */
    public $client;

    /**
     * @var float
     */
    public $timeout = -1;

    /**
     * @var array
     */
    public $setting = [];

    public function recv()
    {
        $this->defer = false;
        $result = TcpPack::decode($this->client->recv($this->timeout), 'rpc');
        Yii::$app->rpc->afterRecv($result);
        Yii::$container->get('tcpclient')->recycle($this->client);
        if ($result instanceof \Exception) {
            throw $result;
        }
        return $result;
    }

    public function __call($name, $params)
    {
        $data = [];
        list($data['service'], $data['route']) = Yii::$app->rpc->getService();
        $server = Yii::$app->gr->provider->getServices($data['service']);
        list($server, $port) = Yii::$app->gr->balance->select($data['service'])->getCurrentService($server);
        $key = sprintf('rpc:%s:%d', $server, $port);
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
                    'setting' => $this->setting,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])
                ->fetch($key);
        }

        $data['method'] = $name;
        $data['params'] = array_shift($params);
        $data['fastCall'] = Yii::$app->rpc->fastCall;
        $data = Yii::$app->rpc->beforeSend($data);
        $this->client->send(TcpPack::encode($data, 'rpc'));
        $id = CoroHelper::getId();
        if (isset($this->defer[$id]) && $this->defer[$id]) {
            $this->defer[$id] = false;
            return $this;
        }
        return $this->recv();
    }
}

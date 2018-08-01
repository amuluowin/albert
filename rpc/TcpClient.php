<?php

namespace yii\swoole\rpc;

use Swoole\Coroutine\Client;
use Yii;
use yii\base\Exception;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\governance\provider\ProviderInterface;
use yii\swoole\pack\TcpPack;
use yii\swoole\pool\TcpPool;
use yii\web\NotFoundHttpException;

class TcpClient extends IRpcClient implements ICoroutine
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
        $result = TcpPack::decode($this->client->recv($this->timeout), 'rpc');
        Yii::$app->rpc->afterRecv($result);
        $this->release();
        if ($result instanceof \Exception) {
            throw $result;
        }
        return $result;
    }

    public function __call($name, $params)
    {
        $data = [];
        list($data['service'], $data['route']) = Yii::$app->rpc->getService();
        /**
         * @var ProviderInterface $provider
         */
        $provider = Yii::$app->gr->provider;
        $server = $provider->getServices($data['service'], $provider->servicePrefix);
        if (empty($server)) {
            $provider->delService($data['service']);
            throw new NotFoundHttpException(sprintf('No service %s alive.', $data['service']));
        }
        list($server, $port) = Yii::$app->gr->balance->select($data['service'])->getCurrentService($server);
        $data['toHost'] = $server . ':' . $port;
        $key = sprintf('rpc:%s:%d', $server, $port);
        if (!Yii::$container->hasSingleton('tcpclient')) {
            Yii::$container->setSingleton('tcpclient', [
                'class' => TcpPool::class
            ]);
        }
        try {
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
            if ($this->IsDefer) {
                $this->IsDefer = false;
                return $this;
            }
            return $this->recv();
        } catch (Exception $e) {
            $provider->delService($data['service']);
            throw $e;
        }
    }

    public function release()
    {
        if (Yii::$container->hasSingleton('tcpclient') && $this->client) {
            Yii::$container->get('tcpclient')->recycle($this->client);
            $this->client = null;
        }
    }
}

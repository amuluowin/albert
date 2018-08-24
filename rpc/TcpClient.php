<?php

namespace yii\swoole\rpc;

use Swoole\Coroutine\Client;
use Yii;
use yii\base\Exception;
use yii\helpers\VarDumper;
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

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var int
     */
    public $retryTotal = 3;

    /**
     * @var int
     */
    private $retryCount = 0;

    public function recv()
    {
        $result = TcpPack::decode($this->client->recv($this->timeout), 'rpc');
        if ($result instanceof \stdClass) {
            Yii::$app->rpc->afterRecv(VarDumper::dumpAsString($result));
            throw new \BadFunctionCallException(sprintf('call to service:%s,route:%s,method:%s.error!message=%s',
                $this->data['service'], $this->data['route'], $this->data['method'], $result->message));
        } else {
            Yii::$app->rpc->afterRecv($result);
        }
        $this->release();
        return $result;
    }

    public function __call($name, $params)
    {
        list($this->data['service'], $this->data['route']) = Yii::$app->rpc->getService();
        /**
         * @var ProviderInterface $provider
         */
        $provider = Yii::$app->gr->provider;
        $server = $provider->getServices($this->data['service'], $provider->servicePrefix);
        if (empty($server)) {
            $provider->delService($this->data['service']);
            throw new NotFoundHttpException(sprintf('No service %s alive.', $this->data['service']));
        }
        list($server, $port) = Yii::$app->gr->balance->select($this->data['service'])->getCurrentService($server);
        $this->data['toHost'] = $server . ':' . $port;
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

            $this->data['method'] = $name;
            $this->data['params'] = $params[0];
            $this->data['fastCall'] = Yii::$app->rpc->fastCall;
            $this->data = Yii::$app->rpc->beforeSend($this->data);
            $this->client->send(TcpPack::encode($this->data, 'rpc'));
            if ($this->IsDefer) {
                $this->IsDefer = false;
                return $this;
            }
            return $this->recv();
        } catch (Exception $e) {
            $provider->delService($this->data['service']);
            Yii::$container->get('tcpclient')->delete($key);
            $this->client->close();
            $this->retryCount++;
            if ($this->retryCount === $this->retryTotal) {
                $this->retryCount = 0;
                throw $e;
            } else {
                $this->__call($name, $params);
            }
        }
    }

    public function release()
    {
        if (Yii::$container->hasSingleton('tcpclient') && $this->client) {
            Yii::$container->get('tcpclient')->recycle($this->client);
            $this->client = null;
            $this->data = [];
        }
    }
}

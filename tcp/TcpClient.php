<?php

namespace yii\swoole\tcp;

use Yii;
use yii\swoole\coroutine\BaseClient;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CoroHelper;

class TcpClient extends BaseClient implements ICoroutine
{
    /**
     * @var float
     */
    public $timeout = -1;
    /**
     * @var array
     */
    public $pack = ['yii\swoole\pack\TcpPack', 'tcp'];
    /**
     * @var array
     */
    public $client;

    public function recv(float $timeout = 0)
    {
        $this->trigger(self::EVENT_BEFORE_RECV);
        $result = $this->client->recv($timeout ?: $this->timeout);
        list($class, $params) = $this->pack;
        $class = Yii::createObject($class);
        $result = $class->decode(...[$result, $params]);
        $this->trigger(self::EVENT_AFTER_RECV);
        $this->release();
        return $result;
    }

    public function send($uri, $port, $data)
    {
        $key = sprintf('tcp:%s%d', $uri, $port);
        if (!Yii::$container->hasSingleton('tcpclient')) {
            Yii::$container->setSingleton('tcpclient', [
                'class' => 'yii\swoole\pool\TcpPool'
            ]);

        }
        if (($this->client = Yii::$container->get('tcpclient')->fetch($key)) === null) {
            $this->client = Yii::$container->get('tcpclient')->create($key,
                [
                    'hostname' => $uri,
                    'port' => $port,
                    'timeout' => $this->timeout,
                    'setting' => $this->setting,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])
                ->fetch($key);
        }

        $this->trigger(self::EVENT_BEFORE_SEND);
        list($class, $params) = $this->pack;
        $class = Yii::createObject($class);
        $data = $class->encode(...([$this->getData(), $params]));
        $this->client->send($data);
        $this->trigger(self::EVENT_AFTER_SEND);
        if ($this->IsDefer) {
            $this->IsDefer = false;
            return clone $this;
        }
        return $this->recv();
    }

    public function release()
    {
        if (Yii::$container->hasSingleton('tcpclient') && $this->client) {
            Yii::$container->get('tcpclient')->recycle($this->client);
            $this->client = null;
        }
    }

}

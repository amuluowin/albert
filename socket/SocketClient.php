<?php

namespace yii\swoole\socket;

use Yii;
use yii\swoole\coroutine\BaseClient;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CoroHelper;

class SocketClient extends BaseClient implements ICoroutine
{
    public $timeout = 0.5;
    public $async = true;
    private $client = [];
    private $data = [];

    public function getClient()
    {
        $id = CoroHelper::getId();
        return isset($this->client[$id]) ? $this->client[$id] : null;
    }

    public function setClient($value)
    {
        $id = CoroHelper::getId();
        $this->client[$id] = $value;
    }

    public function getData()
    {
        $id = CoroHelper::getId();
        return isset($this->data[$id]) ? $this->data[$id] : [];
    }

    public function setData($value)
    {
        $id = CoroHelper::getId();
        $this->data[$id] = $value;
    }

    public function recv()
    {
        $this->trigger(self::EVENT_BEFORE_RECV);
        $result = $this->getClient()->recv();
        $this->setData($result);
        $this->trigger(self::EVENT_AFTER_RECV);
        $this->release();
        return $result;
    }

    public function send($uri, $port, $data)
    {
        $serverIp = ip2long($uri);
        $key = md5('corosocket:' . $serverIp);
        if (!Yii::$container->hasSingleton('socketclient')) {
            Yii::$container->setSingleton('socketclient', [
                'class' => 'yii\swoole\pool\TcpPool'
            ]);
            $conn = Yii::$container->get('socketclient')->create($key,
                [
                    'hostname' => $uri,
                    'port' => $port,
                    'timeout' => $this->timeout,
                    'async' => $this->async,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])
                ->fetch($key);
        } else {
            $conn = Yii::$container->get('socketclient')->fetch($key);
        }

        $this->setClient($conn);
        $this->setData($data);
        $this->trigger(self::EVENT_BEFORE_SEND);
        $this->getClient()->send($data);
        $this->trigger(self::EVENT_AFTER_SEND);
        return $this;
    }

    public function release()
    {
        $id = CoroHelper::getId();
        if (Yii::$container->hasSingleton('socketclient') && isset($this->client[$id])) {
            Yii::$container->get('socketclient')->recycle($this->client[$id]);
            unset($this->client[$id]);
            unset($this->data[$id]);
        }
    }

}
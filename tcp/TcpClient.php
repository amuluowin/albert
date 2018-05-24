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
    public $timeout = 0.5;
    /**
     * @var array
     */
    public $pack = ['yii\swoole\pack\TcpPack', 'tcp'];
    /**
     * @var array
     */
    private $client = [];
    /**
     * @var array
     */
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
        list($class, $params) = $this->pack;
        $class = Yii::createObject($class);
        $result = $class->decode(...[$result, $params]);
        $this->setData($result);
        $this->trigger(self::EVENT_AFTER_RECV);
        $this->release();
        return $result;
    }

    public function send($uri, $port, $data)
    {
        $key = md5('corotcp:' . $uri);
        if (!Yii::$container->hasSingleton('tcpclient')) {
            Yii::$container->setSingleton('tcpclient', [
                'class' => 'yii\swoole\pool\TcpPool'
            ]);

        }
        if (($conn = Yii::$container->get('tcpclient')->fetch($key)) === null) {
            $conn = Yii::$container->get('tcpclient')->create($key,
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

        $this->setClient($conn);
        $this->setData($data);
        $this->trigger(self::EVENT_BEFORE_SEND);
        list($class, $params) = $this->pack;
        $class = Yii::createObject($class);
        $data = $class->encode(...([$this->getData(), $params]));
        $this->getClient()->send($data);
        $this->trigger(self::EVENT_AFTER_SEND);
        return $this;
    }

    public function release()
    {
        $id = CoroHelper::getId();
        if (Yii::$container->hasSingleton('tcpclient') && isset($this->client[$id])) {
            Yii::$container->get('tcpclient')->recycle($this->client[$id]);
            unset($this->client[$id]);
            unset($this->data[$id]);
        }
    }

}

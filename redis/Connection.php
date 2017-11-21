<?php

namespace yii\swoole\redis;

use Yii;
use yii\helpers\Inflector;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CallHelper;
use yii\swoole\helpers\CoroHelper;

class Connection extends \yii\redis\Connection implements ICoroutine
{
//    //REDIS服务主机IP
//    public $hostname = 'localhost';
//    public $unixSocket = null;
//    //redis服务端口
//    public $port = 6379;
    //序列化
    public $serialize = true;
//    //数据库名
//    public $database = 0;
//    //密码
//    public $password = null;
    //超时
    public $timeout = 0.5;
    //连接
    private $client = [];
    //key
    private $key;

    public $maxPoolSize = 30;
    public $busy_pool = 30;

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

    public function getIsActive()
    {
        return true;
    }

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $this->key = md5($this->hostname . $this->port . $this->database);
    }

    /**
     * 返回Redis实例。
     * @return null|Redis
     */
    public function Open()
    {
        if (($client = $this->getClient()) !== null) {
            return $client;
        }
        if (!Yii::$container->hasSingleton('redisclient')) {
            Yii::$container->setSingleton('redisclient', [
                'class' => 'yii\swoole\pool\RedisPool',
            ]);
            $this->setClient(Yii::$container->get('redisclient')->create($this->key,
                [
                    'hostname' => $this->hostname,
                    'port' => $this->port,
                    'password' => $this->password,
                    'database' => $this->database,
                    'timeout' => $this->timeout,
                    'serialize' => $this->serialize,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])->fetch($this->key));
        } else {
            $this->setClient(Yii::$container->get('redisclient')->fetch($this->key));
        }

        return $this->getClient();
    }

    public function __call($name, $params)
    {
        $redisCommand = strtoupper($name);
        if (in_array($redisCommand, $this->redisCommands)) {
            return $this->executeCommand($redisCommand, $params);
        } else {
            return parent::__call($name, $params);
        }
    }

    public function executeCommand($name, $params = [])
    {
        try {
            $client = $this->Open();
            $client->setDefer();
            $client->{$name}(...$params);
            $result = $client->recv();
            $this->release();
            return $result;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->release();
        }

    }

    public function send($name, $db, $data)
    {
        $redisCommand = strtoupper(Inflector::camel2words($name, false));
        if (in_array($redisCommand, $this->redisCommands)) {
            $client = $this->Open();
            $client->setDefer();
            $client->{$name}(...$data);
            return $this;
        }
    }

    public function recv()
    {
        $result = $this->Open()->recv();
        $this->release();
        return $result;
    }

    public function release()
    {
        $id = CoroHelper::getId();
        if (Yii::$container->hasSingleton('redisclient')) {
            Yii::$container->get('redisclient')->recycle($this->getClient());
            unset($this->client[$id]);
        }
    }
}

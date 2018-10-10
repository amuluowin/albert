<?php

namespace yii\swoole\mysql;

use Yii;
use yii\swoole\pool\MysqlPool;

class Connection extends \yii\swoole\db\ConnectionPool
{
    public $timeout = 0;

    public $strict_type = false;

    public $fetch_more = false;

    private $key;

    /**
     * @var Transaction the currently active transaction
     */
    private $_transaction;

    /**
     * @var string
     */
    public $commandClass = 'yii\swoole\mysql\Command';

    public function getTransaction()
    {
        return $this->_transaction && $this->_transaction->getIsActive() ? $this->_transaction : null;
    }

    public function beginTransaction($isolationLevel = null)
    {
        $this->open();

        if (($transaction = $this->getTransaction()) === null) {
            $transaction = $this->_transaction = new Transaction(['db' => $this]);
        }
        $transaction->begin($isolationLevel);

        return $transaction;
    }

    protected function createPdoInstance()
    {
        return $this->getFromPool();
    }

    public function getFromPool()
    {
        if (!Yii::$container->hasSingleton('mysqlclient')) {
            Yii::$container->setSingleton('mysqlclient', [
                'class' => MysqlPool::class,
            ]);
            $uri = str_replace('mysql:', '', $this->dsn);
            $uri = str_replace('host=', '', $uri);
            $uri = str_replace('dbname=', '', $uri);
            $uri = explode(';', $uri);
            $hostAndport = explode(':', array_shift($uri));
            $dbname = array_shift($uri);
            $host = array_shift($hostAndport);
            $port = $hostAndport ?: 3306;
            $this->key = sprintf('mysql:%s:%s:%d', $dbname, $host, $port);
            return Yii::$container->get('mysqlclient')->create($this->key,
                [
                    'host' => $host,
                    'port' => $port,
                    'database' => $dbname,
                    'user' => $this->username,
                    'password' => $this->password,
                    'charset' => $this->charset,
                    'strict_type' => $this->strict_type,
                    'fetch_more' => $this->fetch_more,
                    'timeout' => $this->timeout,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])->fetch($this->key);
        }
        return Yii::$container->get('mysqlclient')->fetch($this->key);

    }

    protected function initConnection()
    {
        $this->trigger(self::EVENT_AFTER_OPEN);
    }
}

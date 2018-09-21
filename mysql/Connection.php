<?php

namespace yii\swoole\mysql;

use Yii;
use yii\base\NotSupportedException;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CoroHelper;
use yii\swoole\pool\MysqlPool;

class Connection extends \yii\swoole\db\ConnectionPool implements ICoroutine
{
    public $timeout = 0;

    public $strict_type = false;

    public $fetch_more = false;

    private $key;

    private $_master = false;

    private $_slave = false;

    private $_schema;

    /**
     * @var Transaction the currently active transaction
     */
    private $_transaction;

    /**
     * @var string
     */
    public $commandClass = 'yii\swoole\mysql\Command';

    public function release($conn = null)
    {
        $id = CoroHelper::getId();
        if (isset($this->pdo[$id])) {
            $this->insertId[$id] = $this->pdo[$id]->insert_id;
            $transaction = $this->getTransaction();
            if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
                return;
            }
            if (Yii::$container->hasSingleton('mysqlclient')) {
                Yii::$container->get('mysqlclient')->recycle($this->pdo[$id]);
                unset($this->pdo[$id]);
                $this->_master->release();
                $this->_slave->release();
                unset($this->_transaction[$id]);
                Yii::info('recyle DB connection:' . $this->dsn);
            }
        }
    }

    public function getTransaction()
    {
        $id = CoroHelper::getId();
        return isset($this->_transaction[$id]) && $this->_transaction[$id]->getIsActive() ? $this->_transaction[$id] : null;
    }

    public function beginTransaction($isolationLevel = null)
    {
        $id = CoroHelper::getId();
        $this->open();

        if (($transaction = $this->getTransaction()) === null) {
            $transaction = $this->_transaction[$id] = new Transaction(['db' => $this]);
        }
        $transaction->begin($isolationLevel);

        return $transaction;
    }

    public function begin()
    {
        $id = CoroHelper::getId();
        $this->pdo[$id]->query('START TRANSACTION');
    }

    public function commit()
    {
        $id = CoroHelper::getId();
        $this->pdo[$id]->query('COMMIT');
    }

    public function rollBack()
    {
        $id = CoroHelper::getId();
        $this->pdo[$id]->query('ROLLBACK');
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

    public function getSchema()
    {
        if ($this->_schema !== null) {
            return $this->_schema;
        } else {
            $driver = $this->getDriverName();
            if (isset($this->schemaMap[$driver])) {
                $config = !is_array($this->schemaMap[$driver]) ? ['class' => $this->schemaMap[$driver]] : $this->schemaMap[$driver];
                $config['db'] = $this;

                return $this->_schema = Yii::createObject($config);
            } else {
                throw new NotSupportedException("Connection does not support reading schema information for '$driver' DBMS.");
            }
        }
    }

    public function getMasterPdo()
    {
        $id = CoroHelper::getId();
        $this->open();
        return $this->pdo[$id];
    }

    public function getMaster()
    {
        if ($this->_master === false) {
            $this->_master = $this->shuffleMasters
                ? $this->openFromPool($this->masters, $this->masterConfig)
                : $this->openFromPoolSequentially($this->masters, $this->masterConfig);
        }

        return $this->_master;
    }

    public function getSlave($fallbackToMaster = true)
    {
        if (!$this->enableSlaves) {
            return $fallbackToMaster ? $this : null;
        }

        if ($this->_slave === false) {
            $this->_slave = $this->openFromPool($this->slaves, $this->slaveConfig);
        }

        return $this->_slave === null && $fallbackToMaster ? $this : $this->_slave;
    }

    public function getSlavePdo($fallbackToMaster = true)
    {
        $id = CoroHelper::getId();
        $db = $this->getSlave(false);
        if ($db === null) {
            return $fallbackToMaster ? $this->getMasterPdo() : null;
        } else {
            return $db->pdo[$id];
        }
    }

    public function close()
    {
        $id = CoroHelper::getId();
        if ($this->_master) {
            if ($this->pdo[$id] === $this->_master->pdo[$id]) {
                unset($this->pdo[$id]);
            }

            $this->_master->close();
            $this->_master = false;
        }

        if (isset($this->pdo[$id])) {
            Yii::trace('Closing DB connection: ' . $this->dsn, __METHOD__);
            unset($this->pdo[$id]);
            $this->_schema = null;
            unset($this->_transaction[$id]);
        }

        if ($this->_slave) {
            $this->_slave->close();
            $this->_slave = false;
        }
    }

    public function __clone()
    {
        $this->_master = false;
        $this->_slave = false;
        $this->_schema = null;
        $this->_transaction = [];
        if (strncmp($this->dsn, 'sqlite::memory:', 15) !== 0) {
            // reset PDO connection, unless its sqlite in-memory, which can only have one connection
            $this->pdo = [];
        }
    }
}

<?php

namespace yii\swoole\mysql;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CoroHelper;

class Connection extends \yii\swoole\db\Connection implements ICoroutine
{
    public $maxPoolSize = 30;
    public $busy_pool = 30;

    public $timeout = 1;

    private $key;

    public $pdo = [];

    private $_master = [];

    private $_slave = [];

    private $_schema;

    public $insertId = [];

    private static $isSchemaLoaded = false;

    public $schemaMap = [
        'mysql' => 'yii\swoole\mysql\mysql\Schema', // MySQL
    ];

    /**
     * @var Transaction the currently active transaction
     */
    private $_transaction;

    /**
     * @var string
     */
    public $commandClass = 'yii\swoole\mysql\Command';

    public function open()
    {
        $id = CoroHelper::getId();
        if (isset($this->pdo[$id]) && $this->pdo[$id] !== null) {
            return;
        }

        if (!empty($this->masters)) {
            $db = $this->getMaster();
            if ($db !== null) {
                $this->pdo[$id] = $db->pdo;
                return;
            } else {
                throw new InvalidConfigException('None of the master DB servers is available.');
            }
        }

        if (empty($this->dsn)) {
            throw new InvalidConfigException('Connection::dsn cannot be empty.');
        }
        $token = 'Opening DB connection: ' . $this->dsn;
        try {
            Yii::info($token, __METHOD__);
            Yii::beginProfile($token, __METHOD__);
            $this->pdo[$id] = $this->createPdoInstance();
            $this->initConnection();
            Yii::endProfile($token, __METHOD__);
        } catch (\PDOException $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), $e->errorInfo, (int)$e->getCode(), $e);
        }
    }

    public function release($conn = null)
    {
        $transaction = $this->getTransaction();
        if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
            return;
        }
        $id = CoroHelper::getId();
        if (Yii::$container->hasSingleton('mysqlclient') && isset($this->pdo[$id])) {
            $this->insertId[$id] = $this->pdo[$id]->insert_id;
            Yii::$container->get('mysqlclient')->recycle($this->pdo[$id]);
            unset($this->pdo[$id]);
            Yii::info('recyle DB connection:' . $this->dsn);
        }
    }

    public function getIsActive()
    {
        $id = CoroHelper::getId();
        return isset($this->pdo[$id]) || isset($this->insertId[$id]);
    }

    public function initSchema()
    {
        if (!static::$isSchemaLoaded && $this->pdo) {
            $this->getSchema()->getTableSchemas();
            static::$isSchemaLoaded = true;
        }
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
        $id = CoroHelper::getId();
        if (!isset($this->_master[$id])) {
            $this->_master[$id] = ($this->shuffleMasters)
                ? $this->openFromPool($this->masters, $this->masterConfig)
                : $this->openFromPoolSequentially($this->masters, $this->masterConfig);
        }

        return $this->_master[$id];
    }

    public function getSlave($fallbackToMaster = true)
    {
        $id = CoroHelper::getId();
        if (!$this->enableSlaves) {
            return $fallbackToMaster ? $this : null;
        }

        if (!isset($this->_slave[$id])) {
            $this->_slave[$id] = $this->openFromPool($this->slaves, $this->slaveConfig);
        }

        return $this->_slave[$id] === null && $fallbackToMaster ? $this : $this->_slave[$id];
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
        $this->pdo[$id]->setDefer();
        $this->pdo[$id]->query('START TRANSACTION');
        $this->pdo[$id]->recv();
    }

    public function commit()
    {
        $id = CoroHelper::getId();
        $this->pdo[$id]->setDefer();
        $this->pdo[$id]->query('COMMIT');
        $this->pdo[$id]->recv();
    }

    public function rollBack()
    {
        $id = CoroHelper::getId();
        $this->pdo[$id]->setDefer();
        $this->pdo[$id]->query('ROLLBACK');
        $this->pdo[$id]->recv();
    }

    protected function createPdoInstance()
    {
        return $this->getFromPool();
    }

    public function getFromPool()
    {
        if (!Yii::$container->hasSingleton('mysqlclient')) {
            Yii::$container->setSingleton('mysqlclient', [
                'class' => 'yii\swoole\pool\MysqlPool',
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

    public function close()
    {
        $id = CoroHelper::getId();
        if (isset($this->_master[$id])) {
            if ($this->pdo[$id] === $this->_master->pdo[$id]) {
                unset($this->pdo[$id]);
            }

            $this->_master->close();
            unset($this->_master[$id]);
        }

        if (isset($this->pdo[$id])) {
            Yii::trace('Closing DB connection: ' . $this->dsn, __METHOD__);
            unset($this->pdo[$id]);
            $this->_schema = null;
            unset($this->_transaction[$id]);
        }

        if (isset($this->_slave[$id])) {
            $this->_slave->close();
            unset($this->_slave[$id]);
        }
    }

    public function __clone()
    {
        $this->_master = [];
        $this->_slave = [];
        $this->_schema = null;
        $this->_transaction = [];
        if (strncmp($this->dsn, 'sqlite::memory:', 15) !== 0) {
            // reset PDO connection, unless its sqlite in-memory, which can only have one connection
            $this->pdo = [];
        }
    }
}

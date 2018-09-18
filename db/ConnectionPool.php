<?php

namespace yii\swoole\db;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\base\NotSupportedException;
use yii\swoole\Application;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CoroHelper;
use yii\swoole\pool\PdoPool;

class ConnectionPool extends Connection implements ICoroutine
{
    public $maxPoolSize = 30;
    public $busy_pool = 30;
    /**
     * @var string driver name
     */
    private $_driverName;

    /**
     * @var Transaction the currently active transaction
     */
    private $_transaction;

    public $pdo = [];

    private $_master = false;

    private $_slave = false;

    private $_schema;

    public $insertId = [];

    /**
     * @var string
     */
    public $commandClass = 'yii\swoole\db\Command';

    public $schemaMap = [
        'mysql' => 'yii\swoole\db\mysql\Schema', // MySQL
    ];

    // add function for pool
    public function release($conn = null)
    {
        $id = CoroHelper::getId();
        if (isset($this->pdo[$id])) {
            $this->insertId[$id] = $this->pdo[$id]->insert_id;
            $transaction = $this->getTransaction();
            if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
                return;
            }
            if (Yii::$container->hasSingleton('pdoclient')) {
                Yii::$container->get('pdoclient')->recycle($this->pdo[$id]);
                unset($this->pdo[$id]);
                $this->_master->release();
                $this->_slave->release();
                unset($this->_transaction[$id]);
                Yii::info('recyle DB connection:' . $this->dsn);
            }
        }
    }

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

    protected function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        if ($pdoClass === null) {
            $pdoClass = 'PDO';
            if ($this->_driverName !== null) {
                $driver = $this->_driverName;
            } elseif (($pos = strpos($this->dsn, ':')) !== false) {
                $driver = strtolower(substr($this->dsn, 0, $pos));
            }
            if (isset($driver)) {
                if ($driver === 'mssql' || $driver === 'dblib') {
                    $pdoClass = 'yii\db\mssql\PDO';
                } elseif ($driver === 'sqlsrv') {
                    $pdoClass = 'yii\db\mssql\SqlsrvPDO';
                }
            }
        }

        $dsn = $this->dsn;
        if (strncmp('sqlite:@', $dsn, 8) === 0) {
            $dsn = 'sqlite:' . Yii::getAlias(substr($dsn, 7));
        }
        if (!Yii::$container->hasSingleton('pdoclient')) {
            Yii::$container->setSingleton('pdoclient', [
                'class' => PdoPool::class,
            ]);

            return Yii::$container->get('pdoclient')->create($this->dsn,
                [
                    'class' => $pdoClass,
                    'dsn' => $dsn,
                    'username' => $this->username,
                    'password' => $this->password,
                    'attributes' => $this->attributes,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])->fetch($this->key);
        }
        return Yii::$container->get('pdoclient')->fetch($this->key);
    }

    public function getIsActive()
    {
        $id = CoroHelper::getId();
        return isset($this->pdo[$id]) || isset($this->insertId[$id]);
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

<?php

namespace yii\swoole\db;

use Yii;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\pool\PdoPool;

class ConnectionPool extends Connection implements ICoroutine
{
    public $maxPoolSize = 30;
    public $busy_pool = 30;
    /**
     * @var string driver name
     */
    private $_driverName;

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
        if ($this->pdo) {
            $this->insertId = $this->pdo->insert_id;
            $transaction = $this->getTransaction();
            if (!empty($transaction) && $transaction->getIsActive()) {//事务里面不释放连接
                return;
            }
            if (Yii::$container->hasSingleton('pdoclient')) {
                Yii::$container->get('pdoclient')->recycle($this->pdo);
                $this->close();
                Yii::info('recyle DB connection:' . $this->dsn);
            }
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
}

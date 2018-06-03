<?php

namespace yii\swoole\pool;

use Yii;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\helpers\VarDumper;
use yii\swoole\base\Output;
use yii\swoole\configcenter\ConfigInterface;
use yii\web\ServerErrorHttpException;

trait PoolTrait
{
    protected $spareConns = [];
    protected $busyConns = [];
    protected $pendingFetchCount = [];
    protected $resumeFetchCount = [];
    protected $connsConfig = [];
    protected $connsNameMap = [];

    protected $reconnect = 3;
    protected $curconnect = 0;
    protected $recycleTime = 1;

    public function create(string $connName, array $config)
    {
        $this->connsConfig[$connName] = $config;
        $this->spareConns[$connName] = new \SplQueue();
        $this->busyConns[$connName] = [];
        $this->pendingFetchCount[$connName] = 0;
        $this->resumeFetchCount[$connName] = 0;
        if ($config['pool_size'] <= 0 || $config['busy_size'] <= 0) {
            throw new InvalidArgumentException("Invalid maxSpareConns or maxConns in {$connName}");
        }
        if (($center = Yii::$app->get('csconf', false)) !== null) {
            $center->putConfig($connName, $config);
            Yii::$confKeys[$connName] = [$this, 'setConfig'];
        }
        return $this;
    }

    public function setConfig(string $connName, array $config)
    {
        if ($config && $this->connsConfig[$connName] !== $config) {
            $this->connsConfig[$connName] = $config;
            Output::writeln(sprintf('%s config changed to %s', $connName, VarDumper::export($config)));
        }
    }

    public function recycle($conn)
    {
        if ($conn !== null) {
            $id = spl_object_hash($conn);
            $connName = $this->connsNameMap[$id];
            if (isset($this->busyConns[$connName][$id])) {
                unset($this->busyConns[$connName][$id]);
            } else {
                Yii::error("Unknow {$connName} connection.");
            }

            if ((($conn instanceof \Swoole\Coroutine\MySQL) && $conn->errno === 0) ||
                (!($conn instanceof \Swoole\Coroutine\MySQL) && $conn->errCode === 0)
            ) {
                if ($this->spareConns[$connName]->count() >= $this->connsConfig[$connName]['busy_size']) {
                    $conn->close();
                } else {
                    $this->spareConns[$connName]->push($conn);
                    if ($this->pendingFetchCount[$connName] > 0) {
                        $this->resumeFetchCount[$connName]++;
                        $this->pendingFetchCount[$connName]--;
                        \Swoole\Coroutine::resume($connName);
                    }
                    return;
                }
            }
            unset($this->connsNameMap[$id]);
        }
    }

    public function getPool(string $id)
    {
        $connName = $this->connsNameMap[$id];
        return $this->spareConns[$connName];
    }

    public function fetch(string $connName)
    {
        if (!isset($this->connsConfig[$connName])) {
            return null;
        }

        $conn = $this->createConn($connName, $this->getConn($connName));

        if ((($conn instanceof \Swoole\Coroutine\MySQL) && $conn->errno != 0) ||
            (!($conn instanceof \Swoole\Coroutine\MySQL) && $conn->errCode != 0)
        ) {
            $id = spl_object_hash($conn);
            unset($this->busyConns[$connName][$id]);
            unset($this->connsNameMap[$id]);
            throw new ServerErrorHttpException('can not connect to ' . var_export($this->connsConfig[$connName]));
        }
        return $conn;
    }

    protected function getConn(string $connName)
    {
        if (!empty($this->spareConns[$connName]) && $this->spareConns[$connName]->count() > $this->resumeFetchCount[$connName]) {
            $conn = $this->spareConns[$connName]->shift();
            $this->busyConns[$connName][spl_object_hash($conn)] = $conn;
            return $conn;
        }

        if (count($this->busyConns[$connName]) + $this->spareConns[$connName]->count() == $this->connsConfig[$connName]['pool_size']) {
            $this->pendingFetchCount[$connName]++;
            if (\Swoole\Coroutine::suspend($connName) == false) {
                $this->pendingFetchCount[$connName]--;
                throw new Exception('Reach max connections! Can not pending fetch!');
            }
            $this->resumeFetchCount[$connName]--;
            if (!empty($this->spareConns[$connName])) {
                $conn = $this->spareConns[$connName]->shift();
                $this->busyConns[$connName][spl_object_hash($conn)] = $conn;
                return $conn;
            } else {
                return false;//should not happen
            }
        }
    }

    protected function saveConn($connName, $conn)
    {
        $id = spl_object_hash($conn);
        $this->connsNameMap[$id] = $connName;
        $this->busyConns[$connName][$id] = $conn;
    }
}
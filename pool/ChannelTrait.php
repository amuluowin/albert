<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/21
 * Time: 10:34
 */

namespace yii\swoole\pool;

use Swoole\Coroutine\Channel;
use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\VarDumper;
use yii\swoole\base\Output;
use yii\swoole\configcenter\ConfigInterface;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

trait ChannelTrait
{
    protected $spareConns = [];
    protected $busyConns = [];
    protected $connsConfig = [];
    protected $connsNameMap = [];

    protected $reconnect = 3;
    protected $curconnect = 0;
    protected $recycleTime = 1;

    public function create(string $connName, array $config)
    {
        if ($config['pool_size'] <= 0 || $config['busy_size'] <= 0) {
            throw new InvalidArgumentException("Invalid maxSpareConns or maxConns in {$connName}");
        }
        $this->connsConfig[$connName] = $config;
        $this->spareConns[$connName] = new Channel($config['pool_size']);
        $this->busyConns[$connName] = [];
        /**
         * @var ConfigInterface $center
         */
        if (($center = Yii::$app->get('csconf', false)) !== null) {
            $data = ArrayHelper::getValueByArray($config, ['pool_size', 'busy_size']);
            $center->putConfig($connName, $data);
            Yii::$confKeys[$connName] = [$this, 'setConfig'];
        }
        return $this;
    }

    public function setConfig(string $connName, array $config)
    {
        $data = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['pool_size', 'busy_size']);
        if ($config && $data !== $config) {
            $this->connsConfig[$connName] = ArrayHelper::merge($this->connsConfig[$connName], $config);
            $this->spareConns[$connName]->capacity = $config['pool_size'];
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
                if ($this->spareConns[$connName]->length() >= $this->connsConfig[$connName]['busy_size'] ||
                    $this->spareConns[$connName]->isFull()) {
                    $conn->close();
                } else {
                    if (!$this->spareConns[$connName]->push($conn)) {
                        $conn->close();
                    }
                    return;
                }
            }
            unset($this->connsNameMap[$id]);
        }
    }

    public function fetch(string $connName)
    {
        if (!isset($this->connsConfig[$connName])) {
            return null;
        }

        if ($this->spareConns[$connName]->isEmpty() && count($this->busyConns[$connName]) === 0) {
            $conn = null;
        } else {
            $conn = $this->spareConns[$connName]->pop($this->recycleTime);
        }

        $conn = $this->createConn($connName, $conn);

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

    protected function saveConn($connName, $conn)
    {
        $id = spl_object_hash($conn);
        $this->connsNameMap[$id] = $connName;
        $this->busyConns[$connName][$id] = $conn;
    }
}
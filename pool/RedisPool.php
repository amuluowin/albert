<?php

namespace yii\swoole\pool;

use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class RedisPool extends \yii\swoole\pool\IPool
{

    public function createConn(string $connName, $conn = null)
    {
        if (!$conn) {
            $cons = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['password', 'database', 'timeout'], [null, 0, 0.5]);
            $conn = new \Swoole\Coroutine\Redis($cons);
            $this->saveConn($connName, $conn);
        }
        $this->reConnect($conn, $connName);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'serialize'],
            ['localhost', 6379, true]);
        if (!$conn->connected && $conn->connect(\Co::gethostbyname($config['hostname']), $config['port'], false) == false
        ) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                throw new ServerErrorHttpException($conn->error);
            } else {
                $this->curconnect++;
                $this->reConnect($conn, $connName);
            }
        }
    }
}

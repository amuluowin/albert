<?php

namespace yii\swoole\pool;

use Yii;
use yii\base\Exception;
use yii\swoole\helpers\ArrayHelper;

class RedisPool extends \yii\swoole\pool\IPool
{

    public function createConn(string $connName, $conn = null)
    {
        if ($conn && $conn->errCode === 0 && $conn->connected) {
            return $conn;
        }
        $this->reConnect($conn, $connName);
        $this->saveConn($connName, $conn);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'serialize'],
            ['localhost', 6379, true]);
        $cons = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['password', 'database', 'timeout'], [null, 0, 0.5]);
        $conn = new \Swoole\Coroutine\Redis(array_filter($cons));
        if ($conn->connect(\Co::gethostbyname($config['hostname']), $config['port'], false) == false
        ) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                $conn->close();
                throw new Exception(sprintf('connect to redis %s:%p error %s:', $config['hostname'], $confi['port'], $conn->error));
            } else {
                $this->curconnect++;
                \Co::sleep(1);
                $conn = null;
                $this->reConnect($conn, $connName);
            }
        }
    }
}

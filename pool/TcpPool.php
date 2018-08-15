<?php

namespace yii\swoole\pool;

use Yii;
use yii\base\Exception;
use yii\swoole\helpers\ArrayHelper;

class TcpPool extends \yii\swoole\pool\IPool
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
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'timeout', 'setting'],
            ['localhost', 0, 0.5, []]);
        $conn = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        if ($config['setting'] && empty($conn->setting)) {
            $conn->set($config['setting']);
        }
        if ($conn->connect($config['hostname'], $config['port'], 1) == false
        ) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                throw new Exception(sprintf("Can not connect to tcp %s:%d error:%s", $config['hostname'], $config['port'], socket_strerror($conn->errCode)));
            } else {
                $this->curconnect++;
                \Co::sleep(1);
                $conn = null;
                $this->reConnect($conn, $connName);
            }
        }
    }
}

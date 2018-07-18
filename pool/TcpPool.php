<?php

namespace yii\swoole\pool;

use Yii;
use yii\base\Exception;
use yii\swoole\helpers\ArrayHelper;

class TcpPool extends \yii\swoole\pool\IPool
{
    public function createConn(string $connName, $conn = null)
    {
        if (!$conn) {
            $conn = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
            $this->saveConn($connName, $conn);
        }
        $this->reConnect($conn, $connName);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'timeout', 'setting'],
            ['localhost', 0, 0.5, []]);
        if ($config['setting'] && empty($conn->setting)) {
            $conn->set($config['setting']);
        }
        if (!$conn->connected && $conn->connect(\Co::gethostbyname($config['hostname']), $config['port'], $config['timeout']) == false
        ) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                throw new Exception(sprintf("Can not connect to tcp %s:%d error:%s", $config['hostname'], $config['port'], $conn->errCode));
            } else {
                $this->curconnect++;
                \Co::sleep(1);
                $this->reConnect($conn, $connName);
            }
        }
    }
}

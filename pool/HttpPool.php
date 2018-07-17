<?php

namespace yii\swoole\pool;

use Yii;
use yii\httpclient\Exception;
use yii\swoole\helpers\ArrayHelper;

class HttpPool extends \yii\swoole\pool\IPool
{
    public function createConn(string $connName, $conn = null)
    {
        if ($conn && $conn->errCode === 0) {
            return $conn;
        }
        $this->reConnect($conn, $connName);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'timeout', 'scheme', 'setting'],
            ['localhost', 80, 0.5, 'http', []]);
        $conn = new \Swoole\Coroutine\Http\Client($config['hostname'], $config['port'], $config['scheme'] === 'https' ? true : false);
        if ($conn->errCode !== 0) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                $conn->close();
                throw new Exception(sprintf('connect to %s:%d error:', $config['hostname'], $config['port'], $conn->error));
            } else {
                $this->curconnect++;
                $conn->close();
                $this->reConnect($conn, $connName);
            }
        }
        $this->saveConn($connName, $conn);
        if (isset($config['setting'])) {
            $conn->set($config['setting']);
        }
    }
}

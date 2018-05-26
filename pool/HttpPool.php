<?php

namespace yii\swoole\pool;

use Yii;
use yii\swoole\helpers\ArrayHelper;

class HttpPool extends \yii\swoole\pool\IPool
{
    public function createConn(string $connName, $conn = null)
    {
        if ($conn) {
            return $conn;
        }
        $this->reConnect($conn, $connName);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'timeout', 'scheme'],
            ['localhost', 80, 0.5, 'http']);
        if ($ret = \Co::gethostbyname($config['hostname'])) {
            $conn = new \Swoole\Coroutine\Http\Client($ret, $config['port'], $config['scheme'] === 'https' ? true : false);
            if ($conn->errCode !== 0) {
                if ($this->reconnect <= $this->curconnect) {
                    $this->curconnect = 0;
                } else {
                    $this->curconnect++;
                    $this->reConnect($conn, $connName);
                }
            }
            $this->saveConn($connName, $conn);
        }
    }
}

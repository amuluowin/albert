<?php

namespace yii\swoole\pool;

use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class TcpPool extends \yii\swoole\pool\IPool
{
    public function createConn(string $connName, $conn = null)
    {
        if (!$conn) {
            $conn = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
            $this->saveConn($connName, $conn);
        }
        $this->reConnect($conn, $connName);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = ArrayHelper::getValueByArray($this->connsConfig[$connName], ['hostname', 'port', 'timeout'],
            [true, 'localhost', Yii::$app->params['swoole']['tcp']['port'], 0.5]);
        if (!$conn->connected && $conn->connect($config['hostname'], $config['port'], $config['timeout']) == false
        ) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                throw new ServerErrorHttpException("Can not connect to {$connName}: " . $config['hostname'] . ':' . $config['port'], $conn->errCode);
            } else {
                $this->curconnect++;
                $this->reConnect($conn, $connName);
            }
        }
    }
}

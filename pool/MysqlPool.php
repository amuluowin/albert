<?php

namespace yii\swoole\pool;

use Yii;
use yii\db\Exception;

class MysqlPool extends \yii\swoole\pool\IPool
{
    public function createConn(string $connName, $conn = null)
    {
        if ($conn && $conn->errCode === 0 && $conn->connected) {
            return $conn;
        }
        $this->reconnect($conn, $connName);
        $this->saveConn($connName, $conn);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $conn = new \Swoole\Coroutine\MySQL();
        $config = $this->connsConfig[$connName];
        $timeout = isset($config['timeout']) ? $config['timeout'] : 1;
        if ($conn->connect([
                'host' => \Co::gethostbyname($config['host']),
                'port' => $config['port'],
                'user' => $config['user'],
                'password' => $config['password'],
                'database' => $config['database'],
                'charset' => isset($config['charset']) ? $config['charset'] : 'utf-8',
                'timeout' => $timeout,
                'strict_type' => $config['strict_type'],
                'fetch_more' => $config['fetch_more']
            ]) == false
        ) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                $conn->close();
                throw new Exception(sprintf('connect to mysql hsot=%s:%d error:%s', $config['host'], $config['port'], $conn->error));
            } else {
                $this->curconnect++;
                \Co::sleep($timeout);
                $conn = null;
                $this->reConnect($conn, $connName);
            }
        }
    }
}

<?php

namespace yii\swoole\pool;

use Yii;
use yii\web\ServerErrorHttpException;

class MysqlPool extends \yii\swoole\pool\IPool
{
    private $reconnect = 3;
    private $curconnect = 0;

    public function createConn(string $connName)
    {
        $conn = new \Swoole\Coroutine\MySQL();
        $this->saveConn($connName, $conn);
        $this->reconnect($conn, $connName);
        return $conn;
    }

    private function reConnect(\Swoole\Coroutine\MySQL &$conn, string $connName)
    {
        $config = $this->connsConfig[$connName];
        if (!$conn->connected && $conn->connect(['host' => $config['host'], 'port' => $config['port'], 'user' => $config['user'], 'password' => $config['password'],
                'database' => $config['database'], 'charset' => isset($config['charset']) ? $config['charset'] : 'utf-8',
                'timeout' => isset($config['timeout']) ? $config['timeout'] : 1]) == false
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

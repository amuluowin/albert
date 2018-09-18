<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/10
 * Time: 17:08
 */

namespace yii\swoole\pool;


class PdoPool extends IPool
{
    public function createConn(string $connName, $conn = null)
    {
        /**
         * @var \PDO $conn
         */
        if ($conn && $conn->errorCode() === 0) {
            return $conn;
        }
        $this->reconnect($conn, $connName);
        $this->saveConn($connName, $conn);
        return $conn;
    }

    protected function reConnect(&$conn, string $connName)
    {
        $config = $this->connsConfig[$connName];
        $conn = new $config['class']($config['dsn'], $config['username'], $config['password'], $config['attributes']);
        return $conn;
    }
}
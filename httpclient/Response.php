<?php

namespace yii\swoole\httpclient;

use Yii;

class Response extends \yii\httpclient\Response
{

    /**
     * @var \Swoole\Coroutine\Http\Client
     */
    private $conn;

    /**
     * @var bool
     */
    private $isGet = false;

    public function getData()
    {
        if (!$this->isGet) {
            $this->recv();
            $this->isGet = true;
        }

        return parent::getData();
    }

    public function setConn(?\Swoole\Coroutine\Http\Client $conn)
    {
        $this->conn = $conn;
    }

    private function recv()
    {
        if ($this->conn) {
            if ($this->conn->errCode === 0) {
                $this->conn->recv();
            }
            $this->setContent(isset($this->conn->body) ? $this->conn->body : null);
            $this->conn->headers['http-code'] = $this->conn->statusCode;
            $this->setHeaders($this->conn->headers);
            $this->setCookies($this->conn->cookies);
            Yii::$container->get('httpclient')->recycle($this->conn);
        } else {
            $this->setHeaders(['http-code' => 0]);
        }
    }

    public function getStatusCode()
    {
        if (!$this->isGet) {
            $this->recv();
            $this->isGet = true;
        }

        return parent::getStatusCode();
    }

}

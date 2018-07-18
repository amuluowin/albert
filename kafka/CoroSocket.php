<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

use Kafka\CommonSocket;
use Yii;
use yii\base\Exception;
use yii\swoole\tcp\TcpClient;

class CoroSocket extends CommonSocket
{
    /**
     * @var SaslMechanism|null
     */
    private $saslProvider;

    public function createStream(): void
    {
        if (trim($this->host) === '') {
            throw new Exception('Cannot open null host.');
        }

        if ($this->port <= 0) {
            throw new Exception('Cannot open without port.');
        }

        $this->stream = new TcpClient(['timeout' => 30]);

        if ($this->saslProvider !== null) {
            $this->saslProvider->authenticate($this);
        }
    }

    public function connect(): void
    {
        if ($this->stream && $this->stream->client && $this->stream->client->connected) {
            return;
        }

        $this->createStream();
    }

    public function close(): void
    {
        $this->stream->client->close();
    }

    /**
     * @param string|int $data
     *
     * @return String|int
     */
    public function read($data)
    {
        return $this->stream->recv($data);
    }

    /**
     * @throws Exception
     */
    public function write(?string $buffer = null)
    {
        if ($buffer === null) {
            throw new Exception('You must inform some data to be written');
        }
        $this->stream->defer()->send($this->host, $this->port, $buffer);
        return $this->stream->client->errCode;
    }
}

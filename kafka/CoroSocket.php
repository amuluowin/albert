<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

use Kafka\SocketSync;
use Yii;
use yii\base\Exception;

class CoroSocket extends SocketSync
{
    /**
     * @var callable|null
     */
    private $onReadable;
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

        $this->stream = Yii::$app->kafka->socket;

        if ($this->saslProvider !== null) {
            $this->saslProvider->authenticate($this);
        }
    }

    public function connect(): void
    {
        if ($this->stream && $this->stream && $this->stream->client->isConnected()) {
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
    public function read($data): string
    {
        return $this->stream->recv();
    }

    /**
     * @throws Exception
     */
    public function write(?string $buffer = null): int
    {
        if ($buffer === null) {
            throw new Exception('You must inform some data to be written');
        }

        return $this->stream->send($this->host, $this->port, $buffer)->client->errCode;
    }
}

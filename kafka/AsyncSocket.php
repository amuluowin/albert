<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

use Kafka\Protocol\Protocol;
use function strlen;
use function substr;

class AsyncSocket extends CoroSocket
{
    /**
     * @var callable|null
     */
    private $onReadable;

    /**
     * @var string
     */
    private $readBuffer = '';

    /**
     * @var int
     */
    private $readNeedLength = 0;

    /**
     * @var int
     */
    private $resource = 0;


    public function reconnect(): void
    {
        $this->close();
        $this->connect();
    }

    public function setOnReadable(callable $read): void
    {
        $this->onReadable = $read;
    }

    public function isResource(): bool
    {
        return (bool)$this->resource;
    }

    /**
     * Read from the socket at most $len bytes.
     *
     * This method will not wait for all the requested data, it will return as
     * soon as any data is received.
     *
     * @param string|int $data
     */
    public function read($data): void
    {
        $this->readBuffer = (string)$data;
        $dataLen = Protocol::unpack(Protocol::BIT_B32, substr($this->readBuffer, 0, 4));
        $this->readBuffer = substr($this->readBuffer, 4);
        ($this->onReadable)($this->readBuffer, (int)$this->resource);
    }

    /**
     * Write to the socket.
     *
     */
    public function write(?string $buffer = null): void
    {
        if ($buffer !== null) {
            $this->connect();
            $this->stream->defer()->send($this->host, $this->port, $buffer);
            $this->resource = $this->stream->client->sock;
            $this->read($this->stream->recv(3));
        }
    }

    public function getSocket()
    {
        return $this->resource;
    }
}

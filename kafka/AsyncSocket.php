<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

use Kafka\CommonSocket;
use Kafka\Exception;
use Kafka\Protocol\Protocol;
use function fclose;
use function feof;
use function fread;
use function is_resource;
use function stream_set_blocking;
use function stream_set_read_buffer;
use function strlen;
use function substr;
use yii\swoole\base\Output;

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
        return is_resource($this->stream->getSocket());
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
        $this->readBuffer .= (string)$data;

        do {
            if ($this->readNeedLength === 0) { // response start
                if (strlen($this->readBuffer) < 4) {
                    return;
                }

                $dataLen = Protocol::unpack(Protocol::BIT_B32, substr($this->readBuffer, 0, 4));
                $this->readNeedLength = $dataLen;
                $this->readBuffer = substr($this->readBuffer, 4);
            }

            if (strlen($this->readBuffer) < $this->readNeedLength) {
                return;
            }

            $data = (string)substr($this->readBuffer, 0, $this->readNeedLength);

            $this->readBuffer = substr($this->readBuffer, $this->readNeedLength);
            $this->readNeedLength = 0;
            ($this->onReadable)($data, (int)$this->stream->client->sock);
        } while (strlen($this->readBuffer));
    }

    /**
     * Write to the socket.
     *
     */
    public function write(?string $buffer = null): void
    {
        if ($buffer !== null) {
            $this->read($this->stream->send($this->host, $this->port, $buffer));
        }
    }

    /**
     * check the stream is close
     */
    protected function isSocketDead(): bool
    {
        return !$this->stream || $this->sockStatus === 0;
    }
}

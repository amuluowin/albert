<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

use Kafka\CommonSocket;
use Kafka\Protocol\Protocol;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function is_resource;
use function stream_set_blocking;
use function stream_set_read_buffer;
use function strlen;
use function substr;

class AsyncSocket extends CommonSocket
{
    /**
     * @var string
     */
    private $readBuffer = '';

    /**
     * @var int
     */
    private $readNeedLength = 0;

    /**
     * @var callable|null
     */
    private $onReadable;

    public function connect(): void
    {
        if (!$this->isSocketDead()) {
            return;
        }

        $this->createStream();

        stream_set_blocking($this->stream, false);
        stream_set_read_buffer($this->stream, 0);

        swoole_event_add($this->stream, function ($fd) {
            $newData = @fread($this->stream, self::READ_MAX_LENGTH);

            if ($newData) {
                $this->read($newData);
            }
        }, function ($fd) {
            $this->write();
        });
    }

    public function reconnect(): void
    {
        $this->close();
        $this->connect();
    }

    public function setOnReadable(callable $read): void
    {
        $this->onReadable = $read;
    }

    public function close(): void
    {
        swoole_event_del($this->stream);
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->readBuffer = '';
        $this->readNeedLength = 0;
    }

    public function isResource(): bool
    {
        return is_resource($this->stream);
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

            ($this->onReadable)($data, (int)$this->stream);
        } while (strlen($this->readBuffer));
    }

    /**
     * Write to the socket.
     *
     */
    public function write(?string $data = null): void
    {
        if ($data !== null) {
            swoole_event_write($this->stream, $data);
        }
    }

    /**
     * check the stream is close
     */
    protected function isSocketDead(): bool
    {
        return !is_resource($this->stream) || @feof($this->stream);
    }
}

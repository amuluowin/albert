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

class AsyncSocket extends CommonSocket
{
    /**
     * @var string
     */
    private $writeData = [];

    /**
     * @var callable|null
     */
    private $onReadable;

    /**
     * @var int
     */
    private $sockStatus = 0;

    public function connect(): void
    {
        if (!$this->isSocketDead()) {
            return;
        }

        $this->createStream();

        $this->stream->on("connect", function ($cli) {
            $this->write();
        });

        $this->stream->on("receive", function ($cli, $data) {
            $this->read($data);
        });

        $this->stream->on("close", function ($cli) {
            $this->sockStatus = 0;
        });

        $this->stream->on("error", function ($cli) {
            $this->sockStatus = 0;
            throw new Exception(
                sprintf('Could not connect to %s:%d (%s [%d])', $this->host, $this->port, @socket_strerror($this->stream->errCode), $this->stream->errCode)
            );
        });

        $this->sockStatus = 1;
        swoole_async_dns_lookup($this->host, function ($host, $ip) {
            $this->host = $ip;
            $this->stream->connect($ip, $this->port, 1);
        });
    }

    /**
     * @throws Exception
     */
    protected function createStream(): void
    {
        if (trim($this->host) === '') {
            throw new Exception('Cannot open null host.');
        }

        if ($this->port <= 0) {
            throw new Exception('Cannot open without port.');
        }

        $this->stream = new \swoole_client(SWOOLE_TCP | SWOOLE_ASYNC);

        $this->stream->set(array(
            'open_length_check' => 1,
            'package_length_type' => 'N',
            'package_length_offset' => 0,       //第N个字节是包长度的值
            'package_body_offset' => 4,       //第几个字节开始计算长度
            'package_max_length' => 2000000,  //协议最大长度
        ));
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
        $this->stream->close();
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
        ($this->onReadable)($data, (int)$this->stream->sock);
    }

    /**
     * Write to the socket.
     *
     */
    public function write(?string $data = null): void
    {
        if ($data && !$this->stream->isConnected()) {
            $this->writeData[] = $data;
        } elseif ($this->stream->isConnected()) {
            $this->writeData[] = $data;
            foreach ($this->writeData as $data) {
                $this->stream->send($data);
            }
            $this->writeData = [];
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

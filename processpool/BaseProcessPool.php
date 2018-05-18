<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-7
 * Time: 上午11:21
 */

namespace yii\swoole\processpool;


use yii\base\Component;

abstract class BaseProcessPool extends Component
{
    public $num = 1;

    public $ipc_type = 0;

    public $msgqueue_key = 0;

    private $pool;

    public $listen = [];

    public $state = 0;

    const RUNNING = 0;
    const PAUSE = 1;
    const STOP = 2;
    const STOPED = 3;

    public function init()
    {
        parent::init();
        $this->pool = new \Swoole\Process\Pool($this->num, $this->ipc_type, $this->msgqueue_key);
        $this->pool->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->pool->on('WorkerStop', [$this, 'onWorkerStop']);
        if ($this->ipc_type === SWOOLE_IPC_SOCKET && $this->listen) {
            $this->pool->on('Message', [$this, 'onMessage']);
            $this->pool->listen($this->listen['host'], $this->listen['port'], $this->listen['backlog']);
        }
    }

    public function start()
    {
        if (!$this->pool->start()) {
            echo swoole_strerror(swoole_errno());
        }
    }

    public function onWorkerStart(\Swoole\Process\Pool $pool, int $workerId)
    {
        \Swoole\Process::signal(SIGTERM, function ($signo) use ($workerId) {
            echo 'shutdown-worker-' . $workerId;
            $this->state = self::STOP;
        });

        if ($this->state === self::RUNNING) {
            switch ($this->ipc_type) {
                case 0:
                    go(function () use ($pool, $workerId) {
                        while ($this->state !== self::STOP) {
                            $this->run($pool, $workerId);
                        }
                    });
                    break;
                case SWOOLE_IPC_SOCKET:
                    go(function () use ($pool, $workerId) {
                        $this->run($pool, $workerId);
                    });
                    break;
                case SWOOLE_IPC_MSGQUEUE:
                    exit(0);
                    break;
            }
        }
    }

    public function onWorkerStop(\Swoole\Process\Pool $pool, int $workerId)
    {
        $this->stop($pool, $workerId);
        $this->state = self::STOPED;
    }

    public function onMessage(\Swoole\Process\Pool $pool, string $data)
    {
        $this->message($pool, $data);
    }

    abstract public function message(\Swoole\Process\Pool $pool, string $data);
}
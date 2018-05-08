<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-7
 * Time: 上午11:21
 */

namespace yii\swoole\processpool;


use yii\base\Component;

class BaseProcessPool extends Component
{
    public $num = 1;

    public $ipc_type = 0;

    public $msgqueue_key = 0;

    public $sleepTime = 0;

    private $pool;

    public $listen = [];

    public function init()
    {
        parent::init();
        $this->pool = new \Swoole\Process\Pool($this->num, $this->ipc_type, $this->msgqueue_key);
        $this->pool->on('WorkerStart', [$this, 'WorkerStart']);
        $this->pool->on('WorkerStop', [$this, 'WorkerStop']);
        $this->pool->on('Message', [$this, 'onMessage'])
        if ($this->listen) {
            $this->pool->listen($this->listen['host'], $this->listen['port'], $this->listen['backlog']);
        }
    }

    public function start()
    {
        if (!$this->pool->start()) {
            echo swoole_errno;
        }
    }

    public function WorkerStart(\Swoole\Process\Pool $pool, int $workerId)
    {
        $running = true;
        \Swoole\Process::signal(SIGTERM, function ($signo) use ($running) {
            echo 'shutdown.';
            $running = false;
        });

        go(function () use ($pool, $workerId) {
            $this->run($pool, $workerId);
        });

        while ($running) {
            if ($this->sleepTime) {
                \Swoole\Coroutine::sleep($this->sleepTime);
            }
        }

    }

    public function WorkerStop(\Swoole\Process\Pool $pool, int $workerId)
    {
        $this->stop($pool, $workerId);
    }

    public function Message(\Swoole\Process\Pool $pool, string $data)
    {
        $this->message($pool, $data);
    }
}
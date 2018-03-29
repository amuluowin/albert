<?php

namespace yii\swoole\dirver;

use React\EventLoop\LoopInterface;
use React\EventLoop\SignalsHandler;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\TimerInterface;

final class React implements LoopInterface
{
    private $futureTickQueue;

    /**
     * @var SignalsHandler
     */
    private $signals;

    public function __construct()
    {
        $this->futureTickQueue = new FutureTickQueue();
        $this->signals = new SignalsHandler();
    }

    /**
     * @param resource $stream
     * @param callable $listener
     * @return bool
     */
    public function addReadStream($stream, $listener)
    {
        if (swoole_event_isset($stream, SWOOLE_EVENT_READ))
        {
            return false;
        }
        if (swoole_event_isset($stream, SWOOLE_EVENT_WRITE))
        {
            return swoole_event_set($stream, $listener, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
        }
        else
        {
            return swoole_event_add($stream, $listener, null, SWOOLE_EVENT_READ);
        }
    }

    /**
     * @param resource $stream
     * @param callable $listener
     * @return bool
     */
    public function addWriteStream($stream, $listener)
    {
        if (swoole_event_isset($stream, SWOOLE_EVENT_WRITE))
        {
            return false;
        }
        if (swoole_event_isset($stream, SWOOLE_EVENT_READ))
        {
            return swoole_event_set($stream, null, $listener, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
        }
        else
        {
            return swoole_event_add($stream, null, $listener, SWOOLE_EVENT_WRITE);
        }
    }

    /**
     * @param resource $stream
     * @return bool
     */
    public function removeReadStream($stream)
    {
        if (!swoole_event_isset($stream, SWOOLE_EVENT_READ))
        {
            return false;
        }
        if (swoole_event_isset($stream, SWOOLE_EVENT_WRITE))
        {
            return swoole_event_set($stream, null, null, SWOOLE_EVENT_WRITE);
        }
        else
        {
            return swoole_event_del($stream);
        }
    }

    /**
     * @param resource $stream
     * @return bool
     */
    public function removeWriteStream($stream)
    {
        if (!swoole_event_isset($stream, SWOOLE_EVENT_WRITE))
        {
            return false;
        }
        if (swoole_event_isset($stream, SWOOLE_EVENT_READ))
        {
            return swoole_event_set($stream, null, null, SWOOLE_EVENT_READ);
        }
        else
        {
            return swoole_event_del($stream);
        }
    }

    /**
     * @param float|int $interval
     * @param callable $callback
     * @return Timer|TimerInterface
     */
    public function addTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, false);
        $timer->id = swoole_timer_after(intval($interval * 1000), $callback);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, true);
        $timer->id = swoole_timer_tick(intval($interval * 1000), $callback);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        swoole_timer_clear($timer->id);
    }

    public function futureTick($listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function addSignal($signal, $listener)
    {
        $first = $this->signals->count($signal) === 0;
        $this->signals->add($signal, $listener);
        if ($first)
        {
            \swoole_process::signal($signal, array($this->signals, 'call'));
        }
    }

    public function removeSignal($signal, $listener)
    {
        if (!$this->signals->count($signal))
        {
            return;
        }
        $this->signals->remove($signal, $listener);
        if ($this->signals->count($signal) === 0)
        {
            \swoole_process::signal($signal, null);
        }
    }

    public function run()
    {
        if (!$this->futureTickQueue->isEmpty())
        {
            swoole_event_cycle(function () {
                $this->futureTickQueue->tick();
            }, true);
        }
        swoole_event_wait();
    }

    public function stop()
    {
        swoole_event_exit();
    }
}
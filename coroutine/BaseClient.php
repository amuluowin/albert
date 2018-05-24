<?php

namespace yii\swoole\coroutine;

abstract class BaseClient extends \yii\base\Component
{
    /**
     * @var int
     */
    public $maxPoolSize = 30;
    /**
     * @var int
     */
    public $busy_pool = 30;

    /**
     * @var array
     */
    public $setting = [];

    const EVENT_BEFORE_SEND = 'beforeSend';
    const EVENT_AFTER_SEND = 'afterSend';

    const EVENT_BEFORE_RECV = 'beforeRecv';
    const EVENT_AFTER_RECV = 'afterRecv';

    abstract public function send($uri, $port, $data);

    abstract public function recv();

    public function sendAndrecv($uri, $port, $data)
    {
        $this->send($uri, $port, $data);
        return $this->recv();
    }

}

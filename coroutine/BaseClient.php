<?php

namespace yii\swoole\coroutine;

use yii\swoole\base\Defer;

abstract class BaseClient extends \yii\base\Component
{
    use Defer;
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

    abstract public function recv(float $timeout = 0);
}

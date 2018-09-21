<?php

namespace yii\swoole\helpers;

class CoroHelper
{
    public static function getId()
    {
        if (PHP_SAPI === 'cli' && is_callable('\Swoole\Coroutine::getuid')) {
            return \Swoole\Coroutine::getuid();
        } else {
            return 0;
        }
    }
}
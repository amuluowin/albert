<?php

namespace yii\swoole\rpc;

use Yii;
use yii\swoole\base\Defer;

abstract class IRpcClient
{
    use Defer;

    abstract public function recv();

    abstract public function __call($name, $arguments);
}

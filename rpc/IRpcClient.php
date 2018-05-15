<?php

namespace yii\swoole\rpc;

use Yii;

interface IRpcClient
{
    public function recv();

    public function __call($name, $arguments);
}

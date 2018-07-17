<?php

namespace yii\swoole\rpc;

use Yii;
use yii\base\Component;
use yii\swoole\base\Defer;

abstract class IRpcClient extends Component
{
    use Defer;

    abstract public function recv();
}

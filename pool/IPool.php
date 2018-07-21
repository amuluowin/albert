<?php

namespace yii\swoole\pool;

use Yii;
use yii\swoole\configcenter\SetConfig;

abstract class IPool extends \yii\base\Component implements SetConfig
{
    use ChannelTrait;

    abstract public function createConn(string $connName, $conn = null);

    abstract protected function reConnect(&$conn, string $connName);
}

<?php

namespace yii\swoole\rpc;

use Yii;
use yii\swoole\helpers\CoroHelper;

abstract class IRpcClient extends \yii\base\Component
{
    protected $service = [];
    protected $fastCall = false;

    public function getService(): array
    {
        return isset($this->service[CoroHelper::getId()]) ? $this->service[CoroHelper::getId()] : [null, null];
    }

    public function fastCall()
    {
        $this->fastCall = true;
        return $this;
    }

    public function create(string $ser, string $route)
    {
        $this->service[CoroHelper::getId()] = [$ser, $route];
        return $this;
    }

    abstract public function recv();
}

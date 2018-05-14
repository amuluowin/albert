<?php

namespace yii\swoole\rpc;

use Yii;
use yii\base\NotSupportedException;

class RpcClient extends IRpcClient
{

    public $config_r;
    public $config_n;

    public $remoteList = [];
    public $selfList = [];

    public function __call($name, $params)
    {
        list($ser, $route) = $this->getService();
        if (key_exists($ser, Yii::$rpcList) && in_array($route, Yii::$rpcList[$ser]) && $this->config_n) {
            $client = clone $this->config_n;
        } else {
            $client = clone $this->config_r;
        }
        $client->create($ser, $route);
        return $client->$name(...$params);
    }

    public function recv()
    {
        throw new NotSupportedException(Yii::t('custom', '不支持此方法调用'));
    }
}

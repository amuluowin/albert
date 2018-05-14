<?php

namespace yii\swoole\rpc;

use Yii;
use yii\swoole\helpers\ArrayHelper;

class NavClient extends IRpcClient
{

    private $data;
    private $method;

    public function recv()
    {
        list($service, $route) = $this->getService();
        $data = call_user_func_array(Yii::$app->RpcHelper->getCurCall($service, $route, $this->method), $this->data);
        return $data;
    }

    public function __call($name, $params)
    {
        $this->method = $name;
        $this->data = $params;
        return $this;
    }
}

<?php

namespace yii\swoole\rpc;

use Yii;

class NavClient implements IRpcClient
{

    private $data;
    private $method;

    public function recv()
    {
        list($service, $route) = Yii::$app->rpc->getService();
        $data = call_user_func_array(Yii::$app->RpcHelper->getCurCall($service, $route, $this->method), $this->data);
        return $data;
    }

    public function __call($name, $params)
    {
        $this->method = $name;
        $this->data = array_shift($params);
        return $this;
    }
}

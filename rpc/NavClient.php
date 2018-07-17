<?php

namespace yii\swoole\rpc;

use Yii;

class NavClient extends IRpcClient
{

    private $data;
    private $method;

    public function recv()
    {
        list($service, $route) = Yii::$app->rpc->getService();
        $data = call_user_func_array(LocalServices::getCurCall($service, $route, $this->method), $this->data);
        return $data;
    }

    public function __call($name, $params)
    {
        $this->method = $name;
        $this->data = $params;
        if ($this->IsDefer) {
            return $this;
        }
        return $this->recv();
    }
}

<?php

namespace yii\swoole\helpers;

use Yii;
use yii\swoole\Module;
use yii\swoole\rpc\RpcRoute;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/8 0008
 * Time: 10:11
 */
class RpcHelper extends \yii\base\Component
{
    private static $_rpcClass;

    /*
     * 获取RPC列表
     */
    public function getRpcClass()
    {
        if (!self::$_rpcClass) {
            self::$_rpcClass = ArrayHelper::merge(RpcRoute::getAppRoutes(), ArrayHelper::getValue(Yii::$app->rpc, 'selfList', []));
        }
        return self::$_rpcClass;
    }


    public function getCurCall(string $service, string $route, string $method)
    {
        $module = Yii::$app->getModule($service);
        if (strpos($route, 'Logic') !== false) {
            $namespace = trim($module->logicNamespace, '\\') . '\\';
            return [$namespace . $route, $method];
        } else {
            $ser = [];
            $this->getRoute($module, $ser);
            return implode('/', $ser);
        }
    }

    private function getRoute(Module $module, array &$service)
    {
        if ($module->module !== Yii::$app) {
            array_push($service, $module->id);
            $this->getModule($module->module, $service);
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午7:43
 */

namespace yii\swoole\governance\balancer;


interface BalancerInterface
{
    public function getCurrentService(array $serviceList, ...$params);
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午7:56
 */

namespace yii\swoole\governance\balancer;


interface BalancerSelectInterface
{
    public function select(string $service);

    public function setBalancer(string $service, BalancerInterface $balancer);
}
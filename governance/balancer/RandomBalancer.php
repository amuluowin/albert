<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午7:43
 */

namespace yii\swoole\governance\balancer;

use yii\base\Component;

class RandomBalancer extends Component implements BalancerInterface
{

    public function getCurrentService(array $serviceList, ...$params)
    {
        $randIndex = array_rand($serviceList);
        return $serviceList[$randIndex];
    }
}
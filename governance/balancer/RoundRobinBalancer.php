<?php

namespace yii\swoole\governance\balancer;

use yii\base\Component;

class RoundRobinBalancer extends Component implements BalancerInterface
{
    /**
     * @var int
     */
    private $lastIndex = 0;

    public function getCurrentService(array $serviceList, ...$params)
    {
        $currentIndex = $this->lastIndex + 1;
        if ($currentIndex + 1 > count($serviceList)) {
            $currentIndex = 0;
        }

        $this->lastIndex = $currentIndex;
        return $serviceList[$currentIndex];
    }
}

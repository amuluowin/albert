<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午7:50
 */

namespace yii\swoole\governance\balancer;

use Yii;
use yii\base\Component;

class BalancerSelecter extends Component implements BalancerSelectInterface
{
    /**
     * @var BalancerInterface
     */
    public $defaultBalancer;
    /**
     * @var array
     */
    protected $serviceBalance = [];

    public function init()
    {
        $services = array_keys(Yii::$rpcList);
        foreach ($services as $service) {
            $this->serviceBalance[$service] = $this->defaultBalancer;
        }
    }

    public function select(string $service)
    {
        return isset($this->serviceBalance[$service]) ? $this->serviceBalance[$service] : $this->defaultBalancer;
    }

    public function setBalancer(string $service, BalancerInterface $balancer)
    {
        $this->serviceBalance[$service] = $balancer;
    }
}
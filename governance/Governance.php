<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午10:50
 */

namespace yii\swoole\governance;

use yii\base\Component;
use yii\swoole\governance\balancer\BalancerSelectInterface;

class Governance extends Component
{
    /**
     * @var TraceInterface
     */
    public $tracer;

    /**
     * @var ProviderInterface
     */
    public $provider;

    /**
     * @var BalancerSelectInterface
     */
    public $balance;
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午11:16
 */

namespace yii\swoole\governance\provider;

use Yii;
use yii\swoole\governance\Governance;
use yii\swoole\process\BaseProcess;

class ProviderProcess extends BaseProcess
{
    /**
     * @var int
     */
    public $ticket = 10;

    /**
     * @var Governance $gr
     */
    private $gr;

    public function init()
    {
        if (!$this->gr instanceof Governance) {
            $this->gr = Yii::$app->get('gr', false);
        }
    }

    public function start()
    {
        $this->register();
    }

    public function register()
    {
        if ($this->gr && !$this->gr->provider->registerService()) {
            swoole_timer_after(1000, [$this, 'register']);
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午11:16
 */

namespace yii\swoole\governance\provider;

use Yii;
use yii\swoole\process\BaseProcess;

class ProviderProcess extends BaseProcess
{
    /**
     * @var int
     */
    public $ticket = 60;

    public function start()
    {
        $this->register();
        swoole_timer_tick($this->ticket * 1000, function () {
            Yii::$app->gr->provider->dnsCheck();
        });
    }

    public function register()
    {
        if (!Yii::$app->gr->provider->registerService()) {
            swoole_timer_after(1000, [$this, 'register']);
        }
    }
}
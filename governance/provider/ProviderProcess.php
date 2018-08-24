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
    public $ticket = 10;

    public function start()
    {
        $this->register();
    }

    public function register()
    {
        if (!Yii::$app->gr->provider->registerService()) {
            swoole_timer_after(1000, [$this, 'register']);
        }
    }
}
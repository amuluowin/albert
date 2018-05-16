<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午11:16
 */

namespace yii\swoole\governance\provider;

use Yii;
use yii\swoole\helpers\SerializeHelper;
use yii\swoole\process\BaseProcess;

class ProviderProcess extends BaseProcess
{
    /**
     * @var int
     */
    public $ticket = 2;

    public function start()
    {
        Yii::$app->gr->provider->registerService();
        swoole_timer_tick($this->ticket * 1000, function () {
            Yii::$app->gr->provider->dnsCheck();
        });
    }
}
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

    public $regTimes = 3;

    public function start()
    {
        $i = 0;
        while (!Yii::$app->gr->provider->registerService()) {
            $i++;
            \Co::sleep(3);
            if ($i === $this->regTimes) {
                break;
            }
        }

        swoole_timer_tick($this->ticket * 1000, function () {
            Yii::$app->gr->provider->dnsCheck();
        });
    }
}
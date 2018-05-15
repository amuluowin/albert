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
    public function start()
    {
        Yii::$app->gr->provider->registerService();
//        $nodes = Yii::$app->gr->provider->dnsCheck();
    }
}
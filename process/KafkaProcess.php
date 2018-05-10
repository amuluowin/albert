<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-2-27
 * Time: 下午5:10
 */

namespace yii\swoole\process;

use Yii;

class KafkaProcess extends BaseProcess
{
    public function start()
    {
        Yii::$app->kafka->startConsumer();
    }
}
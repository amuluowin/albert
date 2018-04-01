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
    public function init()
    {
        $this->server = Yii::$app->getSwooleServer();
    }

    public function start($class, $config)
    {
        $kafkaprocess = new \swoole_process(function ($process) {
            $process->name('swoole-Kafka');
            Yii::$app->kafka->startConsumer();
        }, false, 2);

        $this->server->addProcess($kafkaprocess);
    }
}
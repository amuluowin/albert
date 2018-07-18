<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-18
 * Time: 上午12:07
 */

namespace yii\swoole\kafka;

use Yii;
use yii\swoole\base\BootInterface;
use yii\swoole\server\Server;

class BootWorker implements BootInterface
{

    public function handle(Server $server = null)
    {
        /**
         * @var Kafka $kafka
         */
        if (($kafka = Yii::$app->get('kafka', false)) !== null && !$server->server->taskworker) {
            $kafka->startProducer();
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午8:39
 */

namespace yii\swoole\kafka;

use Psr\Log\LoggerInterface;
use Yii;
use Amp\Loop;
use Kafka\Consumer;
use Kafka\ConsumerConfig;
use yii\base\BaseObject;

class KConsumer extends BaseObject implements IKafkaControl
{
    public function start(LoggerInterface $logger)
    {
        Loop::set(new \yii\swoole\dirver\Amp());
        $config = ConsumerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList('127.0.0.1:9092');
        $config->setGroupId('test');
        $config->setBrokerVersion('1.0.0');
        $config->setTopics(['test']);
        $config->setOffsetReset('earliest');
        $consumer = new Consumer();
        $consumer->setLogger($logger);
        $consumer->start(function ($topic, $part, $message): void {
            var_dump($message);
        });
    }
}


<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午8:39
 */

namespace yii\swoole\kafka;

use Kafka\ConsumerConfig;
use Psr\Log\LoggerInterface;
use Yii;
use yii\base\BaseObject;
use yii\swoole\kafka\Consumer\Consumer;

class KConsumer extends BaseObject implements IKafkaControl
{
    public function start(LoggerInterface $logger = null)
    {
        $config = ConsumerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList('localhost:9092');
        $config->setGroupId('test');
        $config->setBrokerVersion('1.0.0');
        $config->setTopics(['test']);
        $config->setOffsetReset('earliest');
        $consumer = new Consumer();
        if ($logger) {
            $consumer->setLogger($logger);
        }
        $consumer->start(function ($topic, $part, $message): void {
            print_r($message);
        });
    }
}


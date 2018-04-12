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
    public $refreshInterval = 1000;
    public $brokerList = 'localhost:9092';
    public $groupId = 'test';
    public $brokerVersion = '1.0.0';
    public $topics = ['test'];
    public $offsetReset = 'earliest';

    public function start(LoggerInterface $logger = null)
    {
        $config = ConsumerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs($this->refreshInterval);
        $config->setMetadataBrokerList($this->brokerList);
        $config->setGroupId($this->groupId);
        $config->setBrokerVersion($this->brokerVersion);
        $config->setTopics($this->topics);
        $config->setOffsetReset($this->offsetReset);
        $consumer = new Consumer();
        if ($logger) {
            $consumer->setLogger($logger);
        }
        $consumer->start(function ($topic, $part, $message): void {
            print_r($message);
        });
    }
}


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
use yii\base\Component;
use yii\swoole\kafka\Consumer\Consumer;
use yii\swoole\kafka\Targets\Target;

class KConsumer extends Component implements IKafkaControl
{
    public $refreshInterval = 1000;
    public $brokerList = 'localhost:9092';
    public $groupId = 'test';
    public $brokerVersion = '1.0.0';
    public $topics = ['test'];
    public $offsetReset = 'earliest';

    public $targets = [];

    public function init()
    {
        parent::init();
        foreach ($this->targets as $name => $target) {
            if (!$target instanceof Target) {
                $this->targets[$name] = Yii::createObject($target);
            }
        }
    }

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
            foreach ($this->targets as $target) {
                $target->export($topic, $part, $message);
            }
        });
    }
}


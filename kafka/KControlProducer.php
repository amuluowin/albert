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
use Kafka\Producer;
use Kafka\ProducerConfig;
use yii\base\BaseObject;

class KControlProducer extends BaseObject implements IKafkaControl
{
    public function start(LoggerInterface $logger)
    {
        $config = ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(1000);
        $config->setMetadataBrokerList('127.0.0.1:9092');
        $config->setBrokerVersion('1.0.0');
        $config->setRequiredAck(1);
        $config->setIsAsyn(true);
        $config->setProduceInterval(500);

        // control message send interval time
        $message = new Message;
        Loop::repeat(3000, function () use ($message): void {
            $message->setMessage([
                [
                    'topic' => 'test',
                    'value' => 'test....message.' . time(),
                    'key' => '',
                ],
            ]);
        });
        $producer = new Producer(function () use ($message) {
            $tmp = $message->getMessage();
            $message->setMessage([]);
            return $tmp;
        });
        $producer->setLogger($logger);
        $producer->success(function ($result): void {
            var_dump($result);
        });
        $producer->error(function ($errorCode, $context): void {
            var_dump($errorCode);
        });
        $producer->send();
    }
}
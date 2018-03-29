<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午8:54
 */

namespace yii\swoole\kafka;

use Psr\Log\LoggerInterface;
use Yii;
use Kafka\Producer;
use Kafka\ProducerConfig;
use yii\base\BaseObject;

class KProducerSync extends BaseObject implements IKafkaControl
{
    public function start(LoggerInterface $logger)
    {
        $config = ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs(10000);
        $config->setMetadataBrokerList('127.0.0.1:9092');
        $config->setBrokerVersion('1.0.0');
        $config->setRequiredAck(1);
        $config->setIsAsyn(false);
        $config->setProduceInterval(500);
        // if use ssl connect
        //$config->setSslLocalCert('/home/vagrant/code/kafka-php/ca-cert');
        //$config->setSslLocalPk('/home/vagrant/code/kafka-php/ca-key');
        //$config->setSslEnable(true);
        //$config->setSslPassphrase('123456');
        //$config->setSslPeerName('nmred');
        $producer = new Producer();
        $producer->setLogger($logger);
        for ($i = 0; $i < 10; $i++) {
            $result = $producer->send([
                [
                    'topic' => 'test',
                    'value' => 'test1....message. rango',
                    'key' => '',
                ],
            ]);
            var_dump($result);
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午8:50
 */

namespace yii\swoole\kafka;

use Yii;
use yii\base\Component;
use yii\swoole\kafka\Producer\CoroProducer;

class KProducer extends Component
{
    /**
     * @var CoroProducer
     */
    private $producer;

    /**
     * @var int
     */
    public $refreshInterval = 1000;

    /**
     * @var string
     */
    public $brokerList = 'localhost:9092';

    /**
     * @var string
     */
    public $brokerVersion = '1.0.0';

    /**
     * @var int
     */
    public $requiredAck = 1;

    /**
     * @var int
     */
    public $produceInterval = 500;

    public function start()
    {
        $config = ProducerConfig::getInstance();
        $config->setMetadataRefreshIntervalMs($this->refreshInterval);
        $config->setMetadataBrokerList($this->brokerList);
        $config->setBrokerVersion($this->brokerVersion);
        $config->setRequiredAck($this->requiredAck);
        $config->setIsAsyn(false);
        $config->setProduceInterval($this->produceInterval);
//        $config->setSecurityProtocol(Config::SECURITY_PROTOCOL_SASL_SSL);
//        $config->setSaslMechanism(Config::SASL_MECHANISMS_SCRAM_SHA_256);
//        $config->setSaslUsername('nmred');
//        $config->setSaslPassword('123456');
//        $config->setSaslUsername('alice');
//        $config->setSaslPassword('alice-secret');
//        $config->setSaslKeytab('/etc/security/keytabs/kafkaclient.keytab');
//        $config->setSaslPrincipal('kafka/node1@NMREDKAFKA.COM');
        // if use ssl connect
//        $config->setSslLocalCert('/home/vagrant/code/kafka-php/ca-cert');
//        $config->setSslLocalPk('/home/vagrant/code/kafka-php/ca-key');
//        $config->setSslPassphrase('123456');
//        $config->setSslPeerName('nmred');
        $this->producer = new CoroProducer();
    }

    public function send($data): ?array
    {
        if ($this->producer) {
            return $this->producer->send($data);
        }
        return null;
    }
}
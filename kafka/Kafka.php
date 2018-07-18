<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午9:51
 */

namespace yii\swoole\kafka;

use Monolog\Handler\StdoutHandler;
use Psr\Log\LoggerInterface;
use Yii;
use yii\base\Component;
use yii\swoole\tcp\TcpClient;

class Kafka extends Component
{
    /**
     * @var KConsumer
     */
    public $consumer;

    /**
     * @var KProducer
     */
    public $producer;
    public $logger;

    public function init()
    {
        parent::init();

        if (!$this->consumer instanceof KConsumer) {
            $this->consumer = Yii::createObject($this->consumer);
        }

        if (!$this->producer instanceof KProducer) {
            $this->producer = Yii::createObject($this->producer);
        }
    }

    public function startProducer(LoggerInterface $logger = null)
    {
//        if ($logger) {
//            $this->logger = $logger;
//        }
//        if ($this->logger) {
//            $this->logger->pushHandler(new StdoutHandler());
//        }
        $this->producer->start($this->logger);
    }

    public function send(array $data)
    {
        $this->producer->send($data);
    }

    public function startConsumer(LoggerInterface $logger = null)
    {
//        if ($logger) {
//            $this->logger = $logger;
//        }
//        if ($this->logger) {
//            $this->logger->pushHandler(new StdoutHandler());
//        }
        $this->consumer->start($this->logger);
    }
}
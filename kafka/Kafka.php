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

class Kafka extends Component
{
    public $consumer;
    public $producer;
    public $logger;
    public $socket;

    public function init()
    {
        parent::init();

        if (is_array($this->consumer)) {
            $this->consumer = Yii::createObject($this->consumer);
        } else {
            $this->consumer = new KConsumer();
        }

        if (is_array($this->producer)) {
            $this->producer = Yii::createObject($this->producer);
        } else {
            $this->producer = new KProducer();
        }

        if (is_array($this->socket)) {
            $this->socket = Yii::createObject($this->socket);
        }

    }

    public function startProducer(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->logger = $logger;
        }
        if ($this->logger) {
            $this->logger->pushHandler(new StdoutHandler());
        }
        $this->producer->start($this->logger);
    }

    public function startConsumer(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->logger = $logger;
        }
        if ($this->logger) {
            $this->logger->pushHandler(new StdoutHandler());
        }
        $this->consumer->start($this->logger);
    }
}
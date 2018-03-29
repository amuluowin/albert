<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午9:51
 */

namespace yii\swoole\kafka;

use Monolog\Handler\StdoutHandler;
use Monolog\Logger;
use Yii;
use yii\base\Component;

class Kafka extends Component
{
    public $consumer;
    public $producer;
    public $logger;

    public static $logMap = [
        \yii\log\Logger::LEVEL_ERROR => Logger::ERROR,
        \yii\log\Logger::LEVEL_INFO => Logger::INFO,
        \yii\log\Logger::LEVEL_TRACE => Logger::DEBUG,
        \yii\log\Logger::LEVEL_WARNING => Logger::WARNING,
        \yii\log\Logger::LEVEL_PROFILE => Logger::NOTICE,
        \yii\log\Logger::LEVEL_PROFILE_BEGIN => Logger::NOTICE,
        \yii\log\Logger::LEVEL_PROFILE_END => Logger::NOTICE
    ];

    public function init()
    {
        parent::init();
        if (is_array($this->logger)) {
            $this->logger = Yii::createObject($this->logger);
        } else {
            $this->logger = new Logger('swlog');
        }
        $this->logger->pushHandler(new StdoutHandler());
        if (is_array($this->consumer)) {
            $this->consumer = Yii::createObject($this->consumer);
        } else {
            $this->consumer = new KConsumer();
        }
        $this->consumer->start($this->logger);
        if (is_array($this->producer)) {
            $this->producer = Yii::createObject($this->producer);
        } else {
            $this->producer = new KProducer();
        }
        $this->producer->start($this->logger);
    }
}
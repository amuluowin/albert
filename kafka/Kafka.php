<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午9:51
 */

namespace yii\swoole\kafka;

use Yii;
use yii\base\Component;

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

    public function startProducer()
    {
        $this->producer->start();
    }

    public function send(array $data)
    {
        $this->producer->send($data);
    }

    public function startConsumer()
    {
        $this->consumer->start();
    }
}
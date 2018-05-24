<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 2017/8/3
 * Time: 下午8:26
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;
use yii\swoole\mqtt\Util;

class Subscribe extends BaseMessage
{

    protected $type = MessageType::SUBSCRIBE;

    protected $need_message_id = true;

    public function getPayload()
    {
        $buffer = "";
        $topics = $this->getClient()->getTopics();
        /* @var \yii\swoole\mqtt\Topic $topic */
        foreach ($topics as $topic_name => $topic) {
            $buffer .= Util::packLenStr($topic->getTopic());
            $buffer .= chr($topic->getQos());
        }
        return $buffer;
    }

}
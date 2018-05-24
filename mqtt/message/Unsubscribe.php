<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 2017/8/3
 * Time: 下午8:27
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;
use yii\swoole\mqtt\Util;

class Unsubscribe extends BaseMessage
{

    protected $type = MessageType::UNSUBSCRIBE;

    protected $topics = [];

    protected $reserved_flags = 0x02;

    protected $need_message_id = true;

    /**
     * @return array
     */
    public function getTopics()
    {
        return $this->topics;
    }

    /**
     * @param array $topics
     */
    public function setTopics($topics)
    {
        $this->topics = $topics;
    }

    public function getPayload()
    {
        $buffer = "";
        foreach ($this->topics as $topic_name) {
            $buffer .= Util::packLenStr($topic_name);
        }
        return $buffer;
    }
}
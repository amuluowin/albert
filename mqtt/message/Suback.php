<?php
/**
 * Created by PhpStorm.
 * User: jesusslim
 * Date: 2017/8/3
 * Time: 下午8:27
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;
use yii\swoole\mqtt\Util;

class Suback extends BaseMessage
{

    protected $type = MessageType::SUBACK;

    protected $result = [];

    public function decodePayload($data, $pos)
    {
        while (isset($data[$pos])) {
            $this->result[] = ord($data[$pos++]);
        }
    }

    public function getResult()
    {
        return $this->result;
    }

    public function decodeVariableHeader($data, &$pos)
    {
        $message_id = Util::decodeUnsignedShort($data, $pos);
        $this->setMessageId($message_id);
        return true;
    }
}
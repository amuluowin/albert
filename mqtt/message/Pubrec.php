<?php
/**
 * Created by PhpStorm.
 * User: jesusslim
 * Date: 2017/8/3
 * Time: 下午8:26
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;
use yii\swoole\mqtt\Util;

class Pubrec extends BaseMessage
{

    protected $type = MessageType::PUBREC;

    protected $need_message_id = true;

    public function decodeVariableHeader($data, &$pos)
    {
        $message_id = Util::decodeUnsignedShort($data, $pos);
        $this->setMessageId($message_id);
        return true;
    }
}
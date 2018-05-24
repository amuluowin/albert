<?php
/**
 * Created by PhpStorm.
 * User: jesusslim
 * Date: 2017/8/3
 * Time: 下午8:28
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;

class Disconnect extends BaseMessage
{

    protected $type = MessageType::DISCONNECT;

}
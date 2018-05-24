<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 2017/8/3
 * Time: 下午8:27
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;

class Pingreq extends BaseMessage
{

    protected $type = MessageType::PINGREQ;

}
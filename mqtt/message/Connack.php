<?php
/**
 * Created by PhpStorm.
 * User: jesusslim
 * Date: 2017/8/3
 * Time: 下午8:25
 */

namespace yii\swoole\mqtt\message;


use yii\swoole\mqtt\enum\MessageType;

class Connack extends BaseMessage
{

    protected $type = MessageType::CONNACK;

}
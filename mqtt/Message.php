<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 2017/8/3
 * Time: 下午8:23
 */

namespace yii\swoole\mqtt;


use yii\swoole\mqtt\enum\MessageType;
use yii\swoole\mqtt\message\Connack;
use yii\swoole\mqtt\message\Connect;
use yii\swoole\mqtt\message\Disconnect;
use yii\swoole\mqtt\message\MessageInterface;
use yii\swoole\mqtt\message\Pingreq;
use yii\swoole\mqtt\message\Pingresp;
use yii\swoole\mqtt\message\Puback;
use yii\swoole\mqtt\message\Pubcomp;
use yii\swoole\mqtt\message\Publish;
use yii\swoole\mqtt\message\Pubrec;
use yii\swoole\mqtt\message\Pubrel;
use yii\swoole\mqtt\message\Suback;
use yii\swoole\mqtt\message\Subscribe;
use yii\swoole\mqtt\message\Unsuback;
use yii\swoole\mqtt\message\Unsubscribe;

class Message
{

    const CLASS_MAP = [
        MessageType::CONNECT => Connect::class,
        MessageType::CONNACK => Connack::class,
        MessageType::PUBLISH => Publish::class,
        MessageType::PUBACK => Puback::class,
        MessageType::PUBREC => Pubrec::class,
        MessageType::PUBREL => Pubrel::class,
        MessageType::PUBCOMP => Pubcomp::class,
        MessageType::SUBSCRIBE => Subscribe::class,
        MessageType::SUBACK => Suback::class,
        MessageType::UNSUBSCRIBE => Unsubscribe::class,
        MessageType::UNSUBACK => Unsuback::class,
        MessageType::PINGREQ => Pingreq::class,
        MessageType::PINGRESP => Pingresp::class,
        MessageType::DISCONNECT => Disconnect::class
    ];

    /**
     * @param $type
     * @param $client
     * @return MessageInterface|bool
     */
    public static function produce($type, $client)
    {
        $cls = self::CLASS_MAP[$type];
        if ($cls) {
            return new $cls($client);
        }
        return false;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午10:01
 */

namespace yii\swoole\kafka;


use Psr\Log\LoggerInterface;

interface IKafkaControl
{
    public function start(LoggerInterface $logger = null);
}
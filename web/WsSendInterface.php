<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-12
 * Time: 下午5:08
 */

namespace yii\swoole\web;


interface WsSendInterface
{
    public function send($server, $data, $to = null);

    public function sendDataByUser($server, $data);
}
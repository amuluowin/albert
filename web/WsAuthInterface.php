<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-12
 * Time: 下午4:28
 */

namespace yii\swoole\web;


interface WsAuthInterface
{
    public function handShake($server, $request);
}
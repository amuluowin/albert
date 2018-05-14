<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 上午12:00
 */

namespace yii\swoole\governance\trace;


interface TraceInterface
{
    public function getCollect($traceId):?array;

    public function setCollect($traceId, array $collect);
}
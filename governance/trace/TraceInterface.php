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
    public function getCollect(string $traceId,array $collect):?array;

    public function addCollect(string $traceId, array $collect);

    public function flushCollect(string $traceId);
}
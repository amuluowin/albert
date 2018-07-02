<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 11:35
 */

namespace yii\swoole\timertask;


class ParseDate
{
    public static $oneDay = 86400;

    public static function parseByDate(string $startDate = null): array
    {
        return self::parseByTimestamp(strtotime($startDate));
    }

    public static function parseByTimestamp(int $time = null): array
    {
        $result = [];
        $now = time();
        $sec = abs($now - $time);
        if ($sec > 0) {
            $result = [
                'ticket' => $startSec % self::$oneDay,
                'days' => $startSec / self::$oneDay,
            ];
        } else {
            $result = [
                'ticket' => 0,
                'days' => 0
            ];
        }
        return $result;
    }
}
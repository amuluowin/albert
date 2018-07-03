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
        $sec = $time - time();
        if ($sec > 0) {
            $result = [
                'ticket' => $sec % self::$oneDay,
                'days' => intval($sec / self::$oneDay),
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
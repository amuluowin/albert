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

    public static function parseByDate(string $startDate, string $endDate = null): array
    {
        return self::parseByTimestamp(strtotime($startDate), $endDate ? strtotime($endDate) : null);
    }

    public static function parseByTimestamp(int $startTime = null, int $endTime = null): array
    {
        $result = [];
        $now = time();
        $startSec = $startTime ? $startTime - $now : 0;
        $endSec = $endTime ? $now - $endTime : 0;
        if ($startSec <= 0) {
            $result = [
                'start' => [
                    'ticket' => 0,
                    'days' => 0
                ],
            ];
        } else {
            $result = [
                'start' => [
                    'ticket' => $startSec % self::$oneDay,
                    'days' => $startSec / self::$oneDay,
                ]
            ];
        }
        if ($endSec) {
            $result['end'] = [
                'ticket' => $endSec % self::$oneDay,
                'days' => $endSec / self::$oneDay,
            ];
        } else {
            $result['end'] = [
                'ticket' => 0,
                'days' => 0,
            ];
        }
        return $result;
    }
}
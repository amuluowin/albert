<?php

namespace yii\swoole\helpers;

class FileHelper extends \yii\helpers\FileHelper
{
    public static function mbStrSplit($string, $len = 1)
    {
        $start = 0;
        $strlen = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string, $start, $len, "utf8");
            $string = mb_substr($string, $len, $strlen, "utf8");
            $strlen = mb_strlen($string);
        }
        return $array;
    }

    public static function strSplit($string, $len = 1)
    {
        $start = 0;
        $strlen = strlen($string);
        while ($strlen) {
            $array[] = substr($string, $start, $len);
            $string = substr($string, $len, $strlen);
            $strlen = strlen($string);
        }
        return $array;
    }
}

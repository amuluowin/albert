<?php

namespace yii\swoole\helpers;


class ServerHelper
{
    public static function getServer()
    {
        if (extension_loaded('swoole') && PHP_SAPI == 'cli') {
            return $_SERVER[CoroHelper::getId()];
        } else {
            return $_SERVER;
        }
    }

    public static function getServerItem(string $name)
    {
        if (extension_loaded('swoole') && PHP_SAPI == 'cli') {
            return $_SERVER[CoroHelper::getId()][$name];
        } else {
            return $_SERVER[$name];
        }
    }

}
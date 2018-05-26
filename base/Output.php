<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-05-27
 * Time: 1:27
 */

namespace yii\swoole\base;

use Yii;

class Output
{
    const NONE = "\033[0m";

    const BLACK = "\033[0;30m";

    const DARK_GRAY = "\033[1;30m";

    const BLUE = "\033[0;34m";

    const LIGHT_BLUE = "\033[1;34m";

    const GREEN = "\033[0;32m";

    const LIGHT_GREEN = "\033[1;32m";

    const CYAN = "\033[0;36m";

    const LIGHT_CYAN = "\033[1;36m";

    const RED = "\033[0;31m";

    const LIGHT_RED = "\033[1;31m";

    const PURPLE = "\033[0;35m";

    const LIGHT_PURPLE = "\033[1;35m";

    const BROWN = "\033[0;33m";

    const YELLOW = "\033[1;33m";

    const LIGHT_GRAY = "\033[0;37m";

    const WHITE = "\033[1;37m";

    public static function writeln($msg, $color = self::CYAN)
    {
        $msg = date('Y-m-d H:i:s', time()) . '  ' . print_r($msg, true);
        if (Yii::$server && Yii::$server->setting['daemonize']) {
            echo $msg . PHP_EOL;
        } else {
            echo $color . $msg . "\33[0m" . PHP_EOL;
        }
    }
}
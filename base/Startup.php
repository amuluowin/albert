<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-03
 * Time: 13:04
 */

namespace yii\swoole\base;


use yii\httpclient\Client;
use yii\swoole\Application;
use yii\swoole\configcenter\ConfigInterface;

class Startup
{
    public static function StartUp(array $config)
    {
        $application = new Application($config);
        $application::$workerApp = true;
    }
}
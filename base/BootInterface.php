<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午9:42
 */

namespace yii\swoole\base;


use yii\swoole\server\Server;

interface BootInterface
{
    public function handle(Server $server);
}
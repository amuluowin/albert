<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/13
 * Time: 3:10
 */

namespace yii\swoole\helpers;

use Swoole\Table;
use yii\swoole\base\BootInterface;
use yii\swoole\server\Server;

class BootSnowflake implements BootInterface
{
    public function handle(Server $server = null)
    {
        //创建自旋锁
        $server->server->spLock = new \Swoole\Lock(SWOOLE_SPINLOCK);
        $server->server->lastTimestamp = 0;

    }
}
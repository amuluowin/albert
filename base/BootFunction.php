<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午9:45
 */

namespace yii\swoole\base;

use Swoole\Atomic;
use Swoole\Table;
use Yii;
use yii\swoole\server\Server;
use yii\swoole\tablecache\Cache;

class BootFunction implements BootInterface
{

    public function handle(Server $server = null)
    {
        //创建缓存内存表
        $server->server->cacheTable = Cache::initCacheTable(1024);

        //设置线程数量
        swoole_async_set(['thread_num' => 50]);

        //设置原子计数
        $server->server->atomic = new Atomic(0);

        //启动进程
        Yii::$app->initProcess();
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 9:45
 */

namespace yii\swoole\websocket;

use Swoole\Table as SwooleTable;
use yii\swoole\base\BootInterface;
use yii\swoole\memory\Table;
use yii\swoole\server\Server;

class WsBeforeStart implements BootInterface
{
    public function handle(Server $server = null)
    {
        //创建websocket连接内存表
        $table = new Table('wsClient', 1024, ['fd' => [SwooleTable::TYPE_INT, 8]]);
        $table->create();
        $server->server->Tables[$table->getName()] = $table;
    }
}
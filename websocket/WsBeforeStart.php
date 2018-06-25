<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 9:45
 */

namespace yii\swoole\websocket;


use yii\swoole\base\BootInterface;
use yii\swoole\server\Server;
use Swoole\Table;

class WsBeforeStart implements BootInterface
{
    public function handle(Server $server = null)
    {
        //创建websocket连接内存表
        $server->server->clientTable = new Table(1024);
        $server->server->clientTable->column('fd', Table::TYPE_INT, 8);
        $server->server->clientTable->create();
    }
}
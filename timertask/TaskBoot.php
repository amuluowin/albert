<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/26
 * Time: 11:48
 */

namespace yii\swoole\timertask;

use Swoole\Table as SwooleTable;
use yii\swoole\base\BootInterface;
use yii\swoole\memory\Table;
use yii\swoole\server\Server;

class TaskBoot implements BootInterface
{
    public function handle(Server $server = null)
    {
        $table = new Table('TimerTask', 8192, [
            'service' => [SwooleTable::TYPE_STRING, '32'],
            'route' => [SwooleTable::TYPE_STRING, '16'],
            'method' => [SwooleTable::TYPE_STRING, '16'],
            'num' => [SwooleTable::TYPE_INT, '4'],
            'total' => [SwooleTable::TYPE_INT, '4'],
            'retry' => [SwooleTable::TYPE_INT, '2'],
            'taskId' => [SwooleTable::TYPE_INT, '11'],
            'params' => [SwooleTable::TYPE_STRING, '1024']
        ]);
        $table->create();
        $server->server->Tables[$table->getName()] = $table;
    }
}
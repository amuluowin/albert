<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/26
 * Time: 11:48
 */

namespace yii\swoole\timertask;

use Yii;
use Swoole\Table as SwooleTable;
use yii\base\BaseObject;
use yii\base\Component;
use yii\swoole\base\BootInterface;
use yii\swoole\memory\Table;
use yii\swoole\server\Server;
use yii\swoole\timertask\model\TaskModel;

class TaskBoot extends Component implements BootInterface
{
    /**
     * @var int
     */
    public $size = 8192;

    public $tableName = 'TimerTask';

    public function handle(Server $server = null)
    {
        $table = new Table($this->tableName, $this->size, [
            'id' => [SwooleTable::TYPE_INT, '11'],
            'service' => [SwooleTable::TYPE_STRING, '32'],
            'route' => [SwooleTable::TYPE_STRING, '16'],
            'method' => [SwooleTable::TYPE_STRING, '16'],
            'ticket' => [SwooleTable::TYPE_INT, '14'],
            'num' => [SwooleTable::TYPE_INT, '4'],
            'total' => [SwooleTable::TYPE_INT, '4'],
            'taskId' => [SwooleTable::TYPE_INT, '11'],
            'succeceRun' => [SwooleTable::TYPE_INT, '11'],
            'failRun' => [SwooleTable::TYPE_INT, '11'],
            'status' => [SwooleTable::TYPE_INT, '2'],
            'startDate' => [SwooleTable::TYPE_STRING, '20'],
            'endDate' => [SwooleTable::TYPE_STRING, '20'],
            'params' => [SwooleTable::TYPE_STRING, '512']
        ]);
        $table->create();
        $server->server->Tables[$table->getName()] = $table;
    }
}
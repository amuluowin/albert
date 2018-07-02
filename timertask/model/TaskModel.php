<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 17:55
 */

namespace yii\swoole\timertask\model;

class TaskModel extends \yii\swoole\base\Model
{
    const TASK_STOP = 0;
    const TASK_START = 1;
    const TASK_PROCESSING = 2;
    const TASK_FINISH = 3;

    public $id;
    public $service;
    public $route;
    public $method;
    public $ticket = 0;
    public $num = 0;
    public $total = 0;
    public $succeceRun = 0;
    public $failRun = 0;
    public $taskId;
    public $params;
    public $status = 1;
    public $startDate = null;
    public $endDate = null;

    public function rules()
    {
        return [
            [['service', 'route', 'method', 'taskId'], 'required'],
            [['params'], 'safe'],
            [['num', 'retry', 'ticket', 'succeceRun', 'failRun'], 'integer'],
            [['startDate', 'endDate'], 'default', 'value' => null],
            [['num', 'retry', 'ticket'], 'default', 'value' => 0]
        ];
    }
}
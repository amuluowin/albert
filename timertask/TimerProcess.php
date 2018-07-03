<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/3
 * Time: 14:49
 */

namespace yii\swoole\timertask;

use Yii;
use yii\swoole\memory\Table;
use yii\swoole\process\BaseProcess;
use yii\swoole\timertask\model\TaskModel;

class TimerProcess extends BaseProcess
{
    /**
     * @var Table
     */
    public $table;

    /**
     * @var int
     */
    public $ticket = 1;

    public $tableName = 'TimerTask';


    public function start()
    {
        \Swoole\Timer::tick($this->ticket * 1000, function (int $tick_id) {
            $now = time();
            foreach (Yii::$app->timertask->getTasks() as $model) {
                $model = new TaskModel($model);
                $startTime = $model->startDate ? strtotime($model->startDate) : $model->startDate;
                if ($model->taskId === 0 && $model->status === TaskModel::Task_READY && $startTime >= $now) {
                    Yii::$app->timertask->setTaskStatus($model, TaskModel::TASK_PROCESSING);
                    $model->taskId = $model->ticket ? \Swoole\Timer::tick($model->ticket * 1000, [Yii::$app->timertask, 'timerCallback'], $model) : Yii::$app->timertask->timerCallback($model->taskId, $model);
                }

                $endTime = $model->endDate ? strtotime($model->endDate) : $model->endDate;
                if ($model->taskId > 0 && $endTime <= $now) {
                    Yii::$app->timertask->setTaskStatus($model, TaskModel::TASK_FINISH);
                    Yii::$app->timertask->clearTimer($model->taskId, $model);
                }
            }
        });
    }
}
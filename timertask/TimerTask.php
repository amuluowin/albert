<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 12:09
 */

namespace yii\swoole\timertask;

use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\swoole\helpers\SerializeHelper;
use yii\swoole\memory\Table;
use yii\swoole\timertask\model\TaskModel;

class TimerTask extends Component
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var string
     */
    public $tableName = 'TimerTask';

    public $keyPrefix = 'key.';

    public function init()
    {
        $this->table = Yii::$server->Tables[$this->tableName];
    }

    public function getTasks(): array
    {
        $result = [];
        foreach ($this->table->getTable() as $row) {
            if ($row) {
                $row['params'] = SerializeHelper::unserialize($row['params']);
                array_push($result, $row);
            }
        }
        return $result;
    }

    public function setTaskStatus(TaskModel $model, int $status): TaskModel
    {
        $model = $this->getTask($model);
        $model->status = $status;
        $this->saveTask($model, false);
        return $model;
    }

    public function removeTask(TaskModel $model): TaskModel
    {
        $this->clearTimer($model->taskId, $model);
        return $this->getTask($model);
    }

    public function startOnceNow(TaskModel $model)
    {
        return Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
    }

    private function createKey(TaskModel $model): string
    {
        return $this->keyPrefix . md5($model->service . $model->route . $model->method . $item['params']);
    }

    private function saveTask(TaskModel $model, bool $isCheck = true)
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->createKey($model);
        if ($isCheck) {
            $this->checkTask($key);
        }
        $this->table->set($key, $item);
    }

    public function getTask(TaskModel $model): TaskModel
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->createKey($model);
        $item = $this->table->get($key);
        $item['params'] = SerializeHelper::unserialize($item['params']);
        $model->load($item, '');
        return $model;
    }

    public function delTask(TaskModel $model)
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->createKey($model);
        $this->table->del($key);
        return $model;
    }

    private function checkTask(string $key)
    {
        if ($this->table->get($key)) {
            throw new ErrorException('the task existing!');
        }
    }

    public function addTask(TaskModel $model): TaskModel
    {
        $startTime = $model->startDate ? strtotime($model->startDate) : $model->startDate;
        $endTime = $model->endDate ? strtotime($model->endDate) : $model->endDate;
        if ($endTime && ($endTime <= $startTime || $endTime <= time())) {
            throw new ErrorException('The startDate can not leq endDate!');
        }
        $this->saveTask($model);
        return $model;
    }

    public function timerCallback(int $id, TaskModel $model)
    {
        $model = $this->getTask($model);
        if ($model->status !== TaskModel::TASK_PAUSE) {
            $this->setTaskStatus($model, TaskModel::TASK_PROCESSING);
            $model->num++;
            $model->taskId = $id;
            if ($model->total > 0 && $model->num === $model->total) {
                $this->clearTimer($id, $model);
                return $id;
            }
            $result = Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
            if (is_array($result)) {
                if ($result['status']) {
                    $model->succeceRun++;
                } else {
                    $model->failRun++;
                }
            } else {
                $model->failRun++;
            }
            $this->saveTask($model, false);
        }
    }

    public function clearTimer(int $id, TaskModel $model): TaskModel
    {
        \Swoole\Timer::clear($model->taskId);
        $this->delTask($model);
        return $model;
    }
}
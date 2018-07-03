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
use yii\base\Exception;
use yii\swoole\helpers\ArrayHelper;
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

    public function setTaskStatus(TaskModel $model, int $status)
    {
        $model = $this->getTask($model);
        $model->status = $status;
        $this->saveTask($model, false);
    }

    public function removeTask(TaskModel $model)
    {
        $this->clearTimer($model->taskId, $model);
    }

    public function startOnceNow(TaskModel $model)
    {
        return Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
    }

    public function saveTask(TaskModel $model, bool $isCheck = true)
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->keyPrefix . md5($item['params']);
        if ($isCheck) {
            $this->checkTask($key);
        }
        $this->table->set($key, $item);
    }

    public function getTask(TaskModel $model): TaskModel
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->keyPrefix . md5($item['params']);
        $item = $this->table->get($key);
        $item['params'] = SerializeHelper::unserialize($item['params']);
        $model->load($item, '');
        return $model;
    }

    public function delTask(TaskModel $model)
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->keyPrefix . md5($item['params']);
        $this->table->del($key);
    }

    private function checkTask(string $key)
    {
        if ($this->table->get($key)) {
            throw new ErrorException('the task existing!');
        }
    }

    public function addTask(TaskModel $model): array
    {
        $startTime = $model->startDate ? strtotime($model->startDate) : $model->startDate;
        $endTime = $model->endDate ? strtotime($model->endDate) : $model->endDate;
        if ($endTime && ($endTime <= $startTime || $endTime <= time())) {
            throw new ErrorException('The startDate can not leq endDate!');
        }
        $this->saveTask($model);
        return [$startTime, $endTime];
    }

    public function startTimer(TaskModel $model)
    {
        list($startTime, $endTime) = $this->addTask($model);
        $startItem = ParseDate::parseByTimestamp($startTime);
        if ($startItem['ticket']) {
            \Swoole\Timer::after($startItem['ticket'] * 1000, [$this, 'beforeStartTime'], [$startItem, $model, 0]);
        }
        $endItem = ParseDate::parseByTimestamp($endTime);
        if ($endItem['ticket']) {
            \Swoole\Timer::after($endItem['ticket'] * 1000, [$this, 'beforeEndTime'], [$endItem, $model, 0]);
        }
    }

    public function beforeStartTime(array $params)
    {
        /**
         * @var TaskModel $model
         */
        list($timeItem, $model, $num) = $params;
        if ($num === $timeItem['days']) {
            $model->taskId = $model->ticket ? \Swoole\Timer::tick($model->ticket * 1000, [$this, 'timerCallback'], $model) : $this->timerCallback($model->taskId, $model);
        } else {
            \Swoole\Timer::after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], [$timeItem, $model, $num++]);
        }
    }

    public function beforeEndTime(array $params)
    {
        /**
         * @var TaskModel $model
         */
        list($timeItem, $model, $num) = $params;
        if ($num === $timeItem['days']) {
            $model = $this->getTask($model);
            $this->setTaskStatus($model, TaskModel::TASK_FINISH);
            $this->clearTimer($model->taskId, $model);
        } else {
            \Swoole\Timer::after(ParseDate::$oneDay * 1000, [$this, 'beforeEndTime'], [$timeItem, $model, $num++]);
        }
    }

    public function timerCallback(int $id, TaskModel $model): int
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
            if ($result['status'] = 1) {
                $model->succeceRun++;
            } else {
                $model->failRun++;
            }
            $this->saveTask($model, false);
        }
        return $id;
    }

    public function clearTimer(int $id, TaskModel $model): bool
    {
        \Swoole\Timer::clear($model->taskId);
        $this->delTask($model);
        return true;
    }
}
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

    public function getTasks()
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

    public function startOnceNow(TaskModel $model)
    {
        return Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
    }

    private function saveTask(TaskModel $model, bool $isCheck = true)
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->keyPrefix . md5($item['params']);
        if ($isCheck) {
            $this->checkTask($key);
        }
        $this->table->set($key, $item);
    }

    private function getTask(TaskModel $model): TaskModel
    {
        $item = $model->toArray();
        $item['params'] = SerializeHelper::serialize($item['params']);
        $key = $this->keyPrefix . md5($item['params']);
        $item = $this->table->get($key);
        $item['params'] = SerializeHelper::unserialize($item['params']);
        $model->load($item, '');
        return $model;
    }

    private function delTask(TaskModel $model)
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

    public function startTimer(TaskModel $model)
    {
        $this->saveTask($model);
        $startTime = $model->startDate ? strtotime($model->startDate) : $model->startDate;
        $endTime = $model->endDate ? strtotime($model->endDate) : $model->endDate;
        if ($endTime && ($endTime <= $startTime || $endTime <= time())) {
            throw new ErrorException('The startTime can not leq endTime!');
        }
        $timeItem = ParseDate::parseByTimestamp($startTime);
        if ($timeItem['ticket']) {
            $model->ticket ? (Yii::$server ? Yii::$server->after($timeItem['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem, $model, 0]) :
                \Swoole\Timer::after($timeItem['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem, $model, 0])) : $this->timerCallback($model->taskId, $model);
        } else {
            $model->ticket ? (Yii::$server ? Yii::$server->tick($model->ticket * 1000, [$this, 'timerCallback'], $model) :
                \Swoole\Timer::tick($model->ticket * 1000, [$this, 'timerCallback'], $model)) : $this->timerCallback($model->taskId, $model);
        }
    }

    public function beforeStartTime(array $params)
    {
        /**
         * @var TaskModel $model
         */
        list($timeItem, $model, $num) = $params;
        if ($num === $timeItem['days']) {
            Yii::$server ? Yii::$server->tick($model->ticket * 1000, [$this, 'timerCallback'], $model) :
                \Swoole\Timer::tick($model->ticket * 1000, [$this, 'timerCallback'], $model);
            var_dump(123 + $model->taskId);
            $endTime = $model->endDate ? strtotime($model->endDate) : $model->endDate;
            $timeItem = ParseDate::parseByTimestamp($endTime);
            if ($timeItem['ticket']) {
                var_dump(456 + $model->taskId);
                Yii::$server ? Yii::$server->after($timeItem['ticket'] * 1000, [$this, 'beforeEndTime'], [$timeItem, $model, 0]) :
                    \Swoole\Timer::after($timeItem['ticket'] * 1000, [$this, 'beforeEndTime'], [$timeItem, $model, 0]);
            }
        } else {
            Yii::$server ? Yii::$server->after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], [$timeItem, $model, $num++]) :
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
            $this->clearTimer($model->taskId, $model);
        } else {
            Yii::$server ? Yii::$server->after(ParseDate::$oneDay * 1000, [$this, 'beforeEndTime'], [$timeItem, $model, $num++]) :
                \Swoole\Timer::after(ParseDate::$oneDay * 1000, [$this, 'beforeEndTime'], [$timeItem, $model, $num++]);
        }
    }

    public function timerCallback(int $id, TaskModel $model)
    {
        $model = $this->getTask($model);
        $model->num++;
        $model->taskId = $id;
        if ($model->total > 0 && $model->num === $model->total) {
            $this->clearTimer($id, $model);
            return;
        }
        $result = Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
        if ($result['status'] = 1) {
            $model->succeceRun++;
        } else {
            $model->failRun++;
        }
        $this->saveTask($model, false);
    }

    public function clearTimer(int $id, TaskModel $model)
    {
        \Swoole\Timer::clear($model->taskId);
        $this->delTask($model);
    }
}
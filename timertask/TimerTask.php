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

    public function startTimer(TaskModel $model, int $ticket = 0, string $startTime = null, string $endTime = null)
    {
        $this->saveTask($model);
        $startTime = $startDate ? strtotime($startDate) : $startDate;
        $endTime = $endTime ? strtotime($endTime) : $endTime;
        if ($endTime && ($endTime <= $startDate || $endTime <= time())) {
            throw new ErrorException('The endTime can not leq startTime!');
        }
        $timeItem = ParseDate::parseByTimestamp($startTime, $endTime);
        if ($timeItem['start']['ticket'] && $timeItem['start']['days']) {
            $ticket ? Yii::$server ? Yii::$server->after($timeItem['start']['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem['start'], $ticket, $model]) :
                \Swoole\Timer::after($timeItem['start']['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem['start'], $ticket, $model]) : $this->timerCallback($model->taskId, $model);
        } else {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'timerCallback'], $model) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'timerCallback'], $model);
        }
        if ($timeItem['end']['ticket'] && $timeItem['end']['days']) {
            Yii::$server ? Yii::$server->after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], [$timeItem['end'], $model]) :
                \Swoole\Timer::after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], [$timeItem['end'], $model]);
        }
    }

    public function beforeStartTime(array $params, int $num = 0)
    {
        list($timeItem, $ticket, $model) = $params;
        if ($num === $timeItem['days']) {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'timerCallback'], $model) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'timerCallback'], $model);
        } else {
            Yii::$server ? Yii::$server->after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], ...[$params, $num++]) :
                \Swoole\Timer::after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], ...[$params, $num++]);
        }
    }

    public function beforeEndTime(array $params, int $num = 0)
    {
        list($timeItem, $model) = $params;
        if ($num === $timeItem['days']) {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'clearCallback'], $model) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'clearCallback'], $model);
        } else {
            Yii::$server ? Yii::$server->after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], ...[$params, $num++]) :
                \Swoole\Timer::after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], ...[$params, $num++]);
        }
    }

    public function timerCallback(int $id, TaskModel $model)
    {
        $model->num++;
        $model->taskId = $id;
        $this->saveTask($model, false);
        if ($model->total > 0 && $model->num == $model->total) {
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
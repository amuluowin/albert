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
use yii\swoole\timertask\model\TaskModel;

class TimerTask extends Component
{
    public function startOnceNow(TaskModel $model)
    {
        return Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
    }

    public function startTimer(string $startTime, TaskModel $model, int $ticket = 0, string $endTime = null)
    {
        $timeItem = ParseDate::parseByDate($startTime, $endTime);
        if ($timeItem['start']['ticket'] && $timeItem['start']['days']) {
            $ticket ? Yii::$server ? Yii::$server->after($timeItem['start']['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem['start'], $ticket, $model]) :
                \Swoole\Timer::after($timeItem['start']['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem['start'], $ticket, $model]) : $this->timerCallback($model->taskId, $model);
        } else {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'timerCallback'], $model) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'timerCallback'], $model);
        }
        if ($timeItem['end']['ticket'] && $timeItem['end']['days']) {
            Yii::$server ? Yii::$server->after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], $timeItem['end']) :
                \Swoole\Timer::after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], $timeItem['end']);
        }
    }

    public function beforeStartTime(array $params, int $num = 0)
    {
        list($timeItem, $ticket, $model) = $params;
        if ($num === $timeItem['days']) {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'timerCallback'], $model) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'timerCallback'], $model);
        } else {
            Yii::$server ? Yii::$server->after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], $params) :
                \Swoole\Timer::after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], $params);
        }
    }

    public function beforeEndTime(array $params, int $num = 0)
    {
        if ($num === $params['days']) {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'clearCallback'], $model) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'clearCallback'], $model);
        } else {
            Yii::$server ? Yii::$server->after($timeItem['end']['ticket'] * 1000, [$this, 'beforeStartTime'], $params) :
                \Swoole\Timer::after($timeItem['end']['ticket'] * 1000, [$this, 'beforeStartTime'], $params);
        }
    }

    public function timerCallback(int $id, TaskModel $model)
    {
        echo $id . PHP_EOL;
        Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
    }

    public function clearTimer($id)
    {
        \Swoole\Timer::clear($id);
    }
}
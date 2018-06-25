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
    public function startTimer(string $startTime, int $ticket, TaskModel $data, string $endTime = null)
    {
        $timeItem = ParseDate::parseByDate($startTime, $endTime);
        if ($timeItem['start']['ticket'] && $timeItem['start']['days']) {
            Yii::$server ? Yii::$server->after($timeItem['start']['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem['start'], $ticket, $data]) :
                \Swoole\Timer::after($timeItem['start']['ticket'] * 1000, [$this, 'beforeStartTime'], [$timeItem['start'], $ticket, $data]);
        } else {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'timerCallback'], $data) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'timerCallback'], $data);
        }
        if ($timeItem['end']['ticket'] && $timeItem['end']['days']) {
            Yii::$server ? Yii::$server->after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], $timeItem['end']) :
                \Swoole\Timer::after($timeItem['end']['ticket'] * 1000, [$this, 'beforeEndTime'], $timeItem['end']);
        }
    }

    public function beforeStartTime(array $params, int $num = 0)
    {
        list($timeItem, $ticket, $data) = $params;
        if ($num === $timeItem['days']) {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'timerCallback'], $data) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'timerCallback'], $data);
        } else {
            Yii::$server ? Yii::$server->after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], $params) :
                \Swoole\Timer::after(ParseDate::$oneDay * 1000, [$this, 'beforeStartTime'], $params);
        }
    }

    public function beforeEndTime(array $params, int $num = 0)
    {
        if ($num === $params['days']) {
            Yii::$server ? Yii::$server->tick($ticket * 1000, [$this, 'clearCallback'], $data) :
                \Swoole\Timer::tick($ticket * 1000, [$this, 'clearCallback'], $data);
        } else {
            Yii::$server ? Yii::$server->after($timeItem['end']['ticket'] * 1000, [$this, 'beforeStartTime'], $params) :
                \Swoole\Timer::after($timeItem['end']['ticket'] * 1000, [$this, 'beforeStartTime'], $params);
        }
    }

    public function timerCallback(int $id, TaskModel $model)
    {
        Yii::$app->rpc->call($model->service, $model->route)->{$model->method}($model->params);
    }

    public function clearTimer($id)
    {
        \Swoole\Timer::clear($id);
    }
}
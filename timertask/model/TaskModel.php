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
    public $service;
    public $route;
    public $method;
    public $taskId;
    public $params;

    public function rules()
    {
        return [
            [['service', 'route', 'method', 'taskId'], 'required'],
            [['params'], 'safe']
        ];
    }
}
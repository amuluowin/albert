<?php

namespace yii\swoole\clog;

use Yii;
use yii\base\Component;
use yii\swoole\clog\model\MsgModel;

abstract class BaseCollecter extends Component
{
    public function save(MsgModel $model)
    {
        $this->write($model->toArray());
    }

    abstract function write($data);
}
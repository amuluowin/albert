<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-05-31
 * Time: 11:25
 */

namespace yii\swoole\base;


use yii\swoole\helpers\CoroHelper;

trait Defer
{
    private $_isDefer = [];

    public function getIsDefer()
    {
        $id = CoroHelper::getId();
        return $this->_isDefer[$id];
    }

    public function setIsDefer(bool $defer)
    {
        $id = CoroHelper::getId();
        $this->_isDefer[$id] = $defer;
    }

    public function defer()
    {
        $id = CoroHelper::getId();
        $this->_isDefer[$id] = true;
        return $this;
    }
}
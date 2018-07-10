<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-05-31
 * Time: 11:25
 */

namespace yii\swoole\base;

trait Defer
{
    private $_isDefer;

    public function getIsDefer()
    {
        return $this->_isDefer;
    }

    public function setIsDefer(bool $defer)
    {
        $this->_isDefer = $defer;
    }

    public function defer()
    {
        $this->_isDefer = true;
        return $this;
    }
}
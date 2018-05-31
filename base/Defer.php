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
    protected $defer = false;

    public function defer()
    {
        $this->defer = true;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-3
 * Time: 下午10:51
 */

namespace yii\swoole\base;


use yii\base\BaseObject;

abstract class Channel extends BaseObject
{
    public $goer = 1;
    public $capacity = 0;
}
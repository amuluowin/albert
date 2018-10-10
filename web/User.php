<?php

namespace yii\swoole\web;

use Yii;
use yii\swoole\coroutine\ICoroutine;

class User extends \yii\web\User
{
    /**
     * @inheritdoc
     */
    protected function renewAuthStatus()
    {
        Yii::$app->session->open();
    }
}

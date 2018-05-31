<?php

namespace yii\swoole\db;

use Yii;

class ActiveRecord extends \yii\db\ActiveRecord
{
    /*
    * 处理标识
    */
    const ACTION_NEXT = 1;
    const ACTION_RETURN = 2;
    const ACTION_RETURN_COMMIT = 3;

    public $realation = [];

    public $sceneList = [];

    public static function addQuery(&$query, $alias)
    {
    }

    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }
}
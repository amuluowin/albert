<?php

namespace yii\swoole\behaviors;

class DatetimeBehavior extends \yii\behaviors\TimestampBehavior
{
    protected function getValue($event)
    {
        if ($this->value === null) {
            return date('Y-m-d H:i:s', time());
        }

        return parent::getValue($event);
    }
}
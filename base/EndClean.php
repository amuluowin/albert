<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/20
 * Time: 14:37
 */

namespace yii\swoole\base;

use Yii;

class EndClean implements EndInterface
{
    public function clean()
    {
        if (($gc = Yii::$app->get('gc', false)) !== null && $gc->tracer) {
            $gc->tracer->release(Yii::$app->getRequest()->getTraceId());
        }
    }
}
<?php

namespace yii\swoole\debug\remotedebug;

use Yii;
use yii\debug\FlattenException;
use yii\swoole\helpers\SerializeHelper;

/**
 * 调试模块的日志记录器
 *
 * @package yii\swoole\debug\remotedebug
 */
class LogTarget extends \yii\debug\LogTarget
{

    /**
     * @inheritdoc
     */
    public function export()
    {
        \SeasLog::setLogger('summary');
        \SeasLog::setRequestID(Yii::$app->getRequest()->getTraceId());
        Yii::debug(SerializeHelper::serialize($this->collectSummary()));
        foreach ($this->module->panels as $id => $panel) {
            \SeasLog::setLogger($id);
            try {
                Yii::debug(SerializeHelper::serialize($panel->save()));
            } catch (\Exception $exception) {
                Yii::error(SerializeHelper::serialize(new FlattenException($exception)));
            }
        }
    }
}

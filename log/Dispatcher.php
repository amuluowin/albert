<?php

namespace yii\swoole\log;

use Yii;
use yii\swoole\web\ErrorHandler;

/**
 * Class Dispatcher
 *
 * @package yii\swoole\log
 */
class Dispatcher extends \yii\log\Dispatcher
{
    /**
     * @inheritdoc
     */
    public function dispatch($messages, $final)
    {
        // 日志一般在请求结束后写入, 不需要再抛出异常, 直接echo即可
        foreach ($this->targets as $target) {
            if ($target->enabled) {
                try {
                    $target->collect($messages, $final);
                } catch (\Exception $e) {
                    $errorMsg = 'Unable to send log via ' . get_class($target) . ': ' . ErrorHandler::convertExceptionToString($e) . "\n";
                    if (Yii::getLogger() instanceof \yii\swoole\seaslog\Logger) {
                        \SeasLog::error($errorMsg);
                    } else {
                        echo $errorMsg;
                    }
                }
            }
        }
    }
}

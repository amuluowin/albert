<?php

namespace yii\swoole\log;

use Yii;
use yii\swoole\Application;
use yii\swoole\web\ErrorHandler;

/**
 * Class Dispatcher
 *
 * @package yii\swoole\log
 */
class Dispatcher extends \yii\log\Dispatcher
{
    public $isFlush = true;

    /**
     * @inheritdoc
     */
    public function dispatch($messages, $final)
    {
        if (!Application::$workerApp) {
            parent::dispatch($messages, $final);
            return;
        }

        // 日志一般在请求结束后写入, 不需要再抛出异常, 直接echo即可
        foreach ($this->targets as $target) {
            //var_dump(get_class($target));
            if ($target->enabled) {
                try {
                    $target->collect($messages, $final);
                } catch (\Exception $e) {
                    // 日志记录器出错
                    $target->enabled = false;
                    echo 'Unable to send log via ' . get_class($target) . ': ' . ErrorHandler::convertExceptionToString($e) . "\n";
                }
            }
        }
    }
}

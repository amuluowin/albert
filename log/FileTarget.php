<?php

namespace yii\swoole\log;

use yii\swoole\Application;

/**
 * Class FileTarget
 *
 * @package yii\swoole\log
 */
class FileTarget extends \yii\log\FileTarget
{
    /**
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        if (!Application::$workerApp) {
            return parent::getContextMessage();
        }
        // 原来的上下文格式化函数, VarDumper太耗时了, 改成直接print_r, 虽然样式丢失不了, 但是效率提升不少
        $result = [];
        foreach ($this->logVars as $key) {
            if (isset($GLOBALS[$key])) {
                $result[] = "\${$key} = " . print_r($GLOBALS[$key], true);
            }
        }
        return implode("\n\n", $result);
    }
}

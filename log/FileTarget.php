<?php

namespace yii\swoole\log;

use yii\base\InvalidConfigException;
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

    /**
     * @inheritdoc
     */
    public function export()
    {
        if (!Application::$workerApp) {
            parent::export();
            return;
        }
        $text = implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n";
        if (($fp = @fopen($this->logFile, 'a')) === false) {
            throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
        }
        @flock($fp, LOCK_EX);
        if ($this->enableRotation) {
            // clear stat cache to ensure getting the real current file size and not a cached one
            // this may result in rotating twice when cached file size is used on subsequent calls
            clearstatcache();
        }
        if ($this->enableRotation && @filesize($this->logFile) > $this->maxFileSize * 1024) {
            $this->rotateFiles();
        }
        \Swoole\Coroutine::fwrite($fp, $text);
        @flock($fp, LOCK_UN);
        @fclose($fp);
        if ($this->fileMode !== null) {
            @chmod($this->logFile, $this->fileMode);
        }
    }

}

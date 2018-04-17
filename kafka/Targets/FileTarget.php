<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-4-14
 * Time: 下午8:43
 */

namespace yii\swoole\kafka\Targets;

use Yii;
use yii\swoole\files\FileIO;

class FileTarget extends Target
{
    public $logFile;

    public function init()
    {
        if ($this->logFile === null) {
            $this->logFile = Yii::$app->getRuntimePath() . '/logs/app.log';
        } else {
            $this->logFile = Yii::getAlias($this->logFile);
        }
    }

    public function export($topic, $part, $message)
    {
        go(function () use ($message) {
            FileIO::write($this->logFile, 'traceID:' . $message['message']['key'] . ' ' . $message['message']['value'] . PHP_EOL, FILE_APPEND);
        });
    }
}
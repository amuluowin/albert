<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-4-14
 * Time: 下午8:43
 */

namespace yii\swoole\kafka\Targets;

use Yii;

class FileTarget extends Target
{
    /**
     * @var array
     */
    public $target;

    public function export($topic, $part, $message)
    {
        /**
         * @var \yii\swoole\files\FileTarget $target
         */
        if (!$this->target instanceof \yii\swoole\files\FileTarget) {
            $target = clone Yii::createObject($this->target);
        } else {
            $target = clone $this->target;
        }
        $target->logFile = sprintf($target->logFile, $topic, $part);
        go(function () use ($target, $message) {
            $target->export($message['message']['key'] . ':' . $message['message']['value']);
        });
    }
}
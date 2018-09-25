<?php

namespace yii\swoole\seaslog;

use Yii;
use SeasLog;
use yii\swoole\Application;
use yii\swoole\helpers\CoroHelper;

class Logger extends \yii\log\Logger
{
    public function log($message, $level, $category = 'application')
    {

    }

    /**
     * @inheritdoc
     */
    public function flush($final = false)
    {
        $messages = $this->getMessages();
        unset($this->messages[CoroHelper::getId()]);
        if ($this->dispatcher instanceof Dispatcher) {
            $this->dispatcher->dispatch($messages, $final);
        }
    }
}

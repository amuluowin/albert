<?php

namespace yii\swoole\seaslog;

use SeasLog;
use Yii;
use yii\swoole\helpers\ArrayHelper;

class Logger extends \yii\log\Logger
{
    /**
     * @var string
     */
    public $path = '@runtime/logs';

    static private $levelList = [
        self::LEVEL_ERROR => 'error',
        self::LEVEL_WARNING => 'warning',
        self::LEVEL_INFO => 'info',
        self::LEVEL_TRACE => 'debug'
    ];

    public function init()
    {
        parent::init();
        \SeasLog::setBasePath(Yii::getAlias($this->path));
    }

    public function log($message, $level, $category = 'application')
    {
        \SeasLog::setLogger(APP_NAME);
        \SeasLog::setRequestID(Yii::$app->getRequest()->getTraceId());
        if (($method = ArrayHelper::getValue(self::$levelList, $level)) !== null) {
            \SeasLog::$method($message);
        } else {
            \SeasLog::debug($message);
        }
    }

    /**
     * @inheritdoc
     */
    public function flush($final = false)
    {
        if ($this->dispatcher instanceof Dispatcher) {
            $this->dispatcher->dispatch([], $final);
        }
    }
}

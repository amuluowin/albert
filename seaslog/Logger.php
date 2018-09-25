<?php

namespace yii\swoole\seaslog;

use SeasLog;
use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\web\Request;

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
        $this->setLogger();
        $this->setRequestValue();
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

    public function setRequestValue()
    {
        /**
         * @var Request
         */
        $request = Yii::$app->getRequest();
        \SeasLog::setRequestID($request->getTraceId());
        \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_REQUEST_URI, $request->getPathInfo());
        \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_DOMAIN_PORT, $request->getHostInfo());
        \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_REQUEST_METHOD, $request->getMethod());
        \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_CLIENT_IP, $request->getRemoteIP());
    }

    public function setLogger($module = APP_NAME)
    {
        \SeasLog::setLogger($module);
    }
}

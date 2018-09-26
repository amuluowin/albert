<?php

namespace yii\swoole\seaslog;

use SeasLog;
use Yii;
use yii\helpers\VarDumper;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\web\Request;

class Logger extends \yii\log\Logger
{
    /**
     * @var string
     */
    public $path = '@runtime/logs';

    /**
     * @var bool
     */
    public $isFlush = true;

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
        $this->setRequestValue();
        if (!is_string($message)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($message instanceof \Throwable || $message instanceof \Exception) {
                $message = (string)$message;
            } else {
                $message = VarDumper::export($message);
            }
        }
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
        if ($request instanceof Request) {
            \SeasLog::setRequestID($request->getTraceId());
        }
        if (method_exists('\SeasLog', 'setRequestVariable')) {
            \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_REQUEST_URI, $request->getPathInfo());
            \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_DOMAIN_PORT, $request->getHostInfo());
            \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_REQUEST_METHOD, $request->getMethod());
            \SeasLog::setRequestVariable(SEASLOG_REQUEST_VARIABLE_CLIENT_IP, $request->getRemoteIP());
        }
    }

    public function setLogger($module = APP_NAME)
    {
        \SeasLog::setLogger($module);
    }
}

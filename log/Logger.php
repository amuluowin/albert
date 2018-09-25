<?php

namespace yii\swoole\log;

use Yii;
use yii\swoole\Application;
use yii\swoole\helpers\CoroHelper;

class Logger extends \yii\log\Logger
{
    /**
     * @var bool
     */
    public $isFlush = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!Application::$workerApp) {
            parent::init();
        }
    }

    public function getMessages()
    {
        $id = CoroHelper::getId();
        return isset($this->messages[$id]) ? $this->messages[$id] : [];
    }

    public function setMessages($messages)
    {
        $id = CoroHelper::getId();
        $this->messages[$id][] = $messages;
    }

    public function log($message, $level, $category = 'application')
    {
        $time = microtime(true);
        $traces = [];
        if ($this->traceLevel > 0 && is_callable('\Co::getBackTrace')) {
            $count = 0;
            $ts = \Co::getBackTrace(CoroHelper::getId());
            if ($ts !== false) {
                array_pop($ts); // remove the last trace since it would be the entry script, not very useful
                foreach ($ts as $trace) {
                    if (isset($trace['file'], $trace['line']) && strpos($trace['file'], YII2_PATH) !== 0) {
                        unset($trace['object'], $trace['args']);
                        $traces[] = $trace;
                        if (++$count >= $this->traceLevel) {
                            break;
                        }
                    }
                }
            }
        }
        $this->setMessages([$message, $level, $category, $time, $traces, memory_get_usage()]);
        if ($this->flushInterval > 0 && count($this->getMessages()) >= $this->flushInterval) {
            $this->flush();
        }
    }

    /**
     * @inheritdoc
     */
    public function flush($final = false)
    {
        if (!Application::$workerApp) {
            parent::flush($final);
            return;
        }
        $messages = $this->getMessages();
        unset($this->messages[CoroHelper::getId()]);
        if ($this->dispatcher instanceof Dispatcher) {
            // \yii\swoole\log\Dispatcher::dispatch
            $this->dispatcher->dispatch($messages, $final);
        }
    }
}

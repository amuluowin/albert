<?php

namespace yii\swoole\log;

use Yii;
use yii\helpers\ArrayHelper;
use yii\swoole\Application;
use yii\swoole\helpers\CoroHelper;
use yii\swoole\kafka\Kafka;

class Logger extends \yii\log\Logger
{

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
        if (isset(Yii::$app->kafka)) {
            $monoLV = ArrayHelper::getValue(Kafka::$logMap, $level, Yii::$app->kafka->logger::NOTICE);
            Yii::$app->kafka->log($monoLV, $message, [$category]);
        }
        $time = microtime(true);
        $traces = [];
        if ($this->traceLevel > 0) {
            $count = 0;
            $ts = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
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

    public function getProfiling($categories = [], $excludeCategories = [])
    {
        $timings = $this->calculateTimings($this->getMessages());
        if (empty($categories) && empty($excludeCategories)) {
            return $timings;
        }

        foreach ($timings as $i => $timing) {
            $matched = empty($categories);
            foreach ($categories as $category) {
                $prefix = rtrim($category, '*');
                if (($timing['category'] === $category || $prefix !== $category) && strpos($timing['category'], $prefix) === 0) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                foreach ($excludeCategories as $category) {
                    $prefix = rtrim($category, '*');
                    foreach ($timings as $i => $timing) {
                        if (($timing['category'] === $category || $prefix !== $category) && strpos($timing['category'], $prefix) === 0) {
                            $matched = false;
                            break;
                        }
                    }
                }
            }

            if (!$matched) {
                unset($timings[$i]);
            }
        }

        return array_values($timings);
    }
}

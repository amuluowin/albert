<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

use yii\log\Logger;

trait LoggerTrait
{
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param mixed[] $context
     *
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(Logger::LEVEL_ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param mixed[] $context
     *
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(Logger::LEVEL_WARNING, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param mixed[] $context
     *
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(Logger::LEVEL_INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param mixed[] $context
     *
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(Logger::LEVEL_TRACE, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param string  $message
     * @param mixed[] $context
     *
     */
    public function log($level, $message, array $context = []): void
    {
//        $this->logger->log($level, $message, $context);
    }
}

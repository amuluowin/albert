<?php
declare(strict_types=1);

namespace yii\swoole\kafka\Consumer;

use yii\swoole\kafka\Consumer\StopStrategy;
use yii\swoole\kafka\LoggerTrait;
use Psr\Log\LoggerAwareTrait;

class Consumer
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * @var StopStrategy|null
     */
    private $stopStrategy;

    /**
     * @var Process|null
     */
    private $process;

    public function __construct(?StopStrategy $stopStrategy = null)
    {
        $this->stopStrategy = $stopStrategy;
    }

    /**
     * start consumer
     *
     * @access public
     *
     *
     */
    public function start(?callable $consumer = null): void
    {
        if ($this->process !== null) {
            $this->error('Consumer is already being executed');
            return;
        }

        $this->process = $this->createProcess($consumer);
        $this->process->start();
    }

    /**
     * FIXME: remove it when we implement dependency injection
     *
     * This is a very bad practice, but if we don't create this method
     * this class will never be testable...
     *
     * @codeCoverageIgnore
     */
    protected function createProcess(?callable $consumer): Process
    {
        $process = new Process($consumer);

        if ($this->logger) {
            $process->setLogger($this->logger);
        }

        return $process;
    }

    public function stop(): void
    {
        if ($this->process === null) {
            $this->error('Consumer is not running');
            return;
        }

        $this->process->stop();
        $this->process = null;
    }
}

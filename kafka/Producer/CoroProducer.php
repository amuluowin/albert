<?php
declare(strict_types=1);

namespace yii\swoole\kafka\Producer;

use Kafka\LoggerTrait;
use Kafka\Producer\SyncProcess;
use Psr\Log\LoggerAwareTrait;
use yii\base\Exception;
use function is_array;

class CoroProducer
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * @var Process|SyncProcess
     */
    private $process;

    public function __construct()
    {
        $this->process = new CoroProcess();
    }

    /**
     * @param mixed[]|bool $data
     *
     * @return mixed[]|null
     */
    public function send($data = true): ?array
    {
        if ($this->logger) {
            $this->process->setLogger($this->logger);
        }

        if (is_array($data)) {
            return $this->sendSynchronously($data);
        }

        return null;
    }

    /**
     * @param mixed[] $data
     *
     * @return mixed[]
     *
     * @throws Exception
     */
    private function sendSynchronously(array $data): array
    {
        return $this->process->send($data);
    }

    public function syncMeta(): void
    {
        $this->process->syncMeta();
    }
}

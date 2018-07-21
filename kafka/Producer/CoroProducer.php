<?php
declare(strict_types=1);

namespace yii\swoole\kafka\Producer;

use yii\base\Exception;
use yii\swoole\kafka\LoggerTrait;
use yii\swoole\kafka\Producer\SyncProcess;
use function is_array;

class CoroProducer
{
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

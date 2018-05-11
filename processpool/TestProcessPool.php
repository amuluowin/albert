<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-10
 * Time: 上午11:34
 */

namespace yii\swoole\processpool;


class TestProcessPool extends BaseProcessPool implements ProcessPoolInterface
{
    public $ipc_type = SWOOLE_IPC_SOCKET;

    public $listen = [
        'host' => '127.0.0.1',
        'port' => 9510,
        'backlog' => 1024
    ];

    public function run(\Swoole\Process\Pool $pool, int $workerId): bool
    {
        echo $workerId . PHP_EOL;
        return true;
    }

    public function stop(\Swoole\Process\Pool $pool, int $workerId)
    {
        echo 'stop ' . $workerId . PHP_EOL;
    }

    public function message(\Swoole\Process\Pool $pool, string $data)
    {
        go(function () use ($data) {
            echo $data . PHP_EOL;
        });
        $this->state = self::STOP;
        $pool->write($data . PHP_EOL);
    }
}
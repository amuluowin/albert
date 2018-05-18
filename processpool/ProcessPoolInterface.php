<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-7
 * Time: 上午11:33
 */

namespace yii\swoole\processpool;


interface ProcessPoolInterface
{
    public function run(\Swoole\Process\Pool $pool, int $workerId);

    public function stop(\Swoole\Process\Pool $pool, int $workerId);

    public function message(\Swoole\Process\Pool $pool, string $data);
}
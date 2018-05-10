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

    public function run(\Swoole\Process\Pool $pool, int $workerId)
    {
//        echo $workerId . PHP_EOL;
    }

    public function stop(\Swoole\Process\Pool $pool, int $workerId)
    {
//        echo 'stop ' . $workerId . PHP_EOL;
    }
}
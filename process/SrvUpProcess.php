<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-4-15
 * Time: 上午11:36
 */

namespace yii\swoole\process;

use Yii;

class SrvUpProcess extends BaseProcess
{
    public function start($class, $config)
    {
        $p = new \swoole_process(function ($process) {
            $process->name('swoole-srvup');
            swoole_timer_tick(Yii::$app->params['BeatConfig']['Srvhbtick'] * 1000, function () {
                \Swoole\Coroutine::create(function () {
                    Yii::$app->mserver->create();
                    Yii::$app->clearComponents();
                });
            });
        }, false, 2);

        $this->server->addProcess($p);
    }
}
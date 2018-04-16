<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-4-15
 * Time: 上午11:38
 */

namespace yii\swoole\process;


class SrvMonitProcess extends BaseProcess
{

    public function start($class, $config)
    {
        $p = new \swoole_process(function ($process) {
            $process->name('swoole-monit');
            swoole_timer_tick(Yii::$app->params['BeatConfig']['Srvhbtick'] * 1000, function () {
                \Swoole\Coroutine::create(function () {
                    Yii::$app->mserver->dealServer();
                    Yii::$app->clearComponents();
                });
            });
        }, false, 2);

        $this->server->addProcess($p);
    }
}
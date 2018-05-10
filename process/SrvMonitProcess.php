<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-4-15
 * Time: 上午11:38
 */

namespace yii\swoole\process;

use Yii;

class SrvMonitProcess extends BaseProcess
{

    public function start()
    {
        swoole_timer_tick(Yii::$app->params['BeatConfig']['Srvhbtick'] * 1000, function () {
            \Swoole\Coroutine::create(function () {
                Yii::$app->mserver->create();
                Yii::$app->mserver->dealServer();
                Yii::$app->clearComponents();
            });
        });
    }
}
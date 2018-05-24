<?php

namespace yii\swoole\commands;

use Yii;
use yii\console\Controller;
use yii\swoole\server\HproseServer;
use yii\swoole\server\QueueServer;

class SwooleController extends Controller
{

    /**
     * Run swoole http server
     *
     * @param string $app Running app
     * @throws \yii\base\InvalidConfigException
     */
    public function actionHttp($app, $d = 0)
    {
        SwooleCommand::Http($app, $d);
    }

    public function actionWebsocket($app, $d = 0)
    {
        SwooleCommand::Websocket($app, $d);
    }

    public function actionRpc($app, $d = 0)
    {
        SwooleCommand::Rpc($app, $d);
    }

    public function actionTask($app, $d = 0)
    {
        SwooleCommand::Task($app, $d);
    }

    public function actionProcess($app, $work)
    {
        SwooleCommand::Process($app, $work);
    }
}

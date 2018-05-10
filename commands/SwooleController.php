<?php

namespace yii\swoole\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\swoole\helpers\IniHelper;
use yii\swoole\server\HproseServer;
use yii\swoole\server\HttpServer;
use yii\swoole\server\ProcessServer;
use yii\swoole\server\QueueServer;
use yii\swoole\server\TaskServer;
use yii\swoole\server\TcpServer;
use yii\swoole\server\WebsocketServer;

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

    public function actionTcp($app, $d = 0)
    {
        SwooleCommand::Tcp($app, $d);
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

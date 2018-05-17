<?php

namespace yii\swoole\commands;

use Yii;
use yii\swoole\server\HproseServer;
use yii\swoole\server\HttpServer;
use yii\swoole\server\ProcessServer;
use yii\swoole\server\QueueServer;
use yii\swoole\server\RpcServer;
use yii\swoole\server\TaskServer;
use yii\swoole\server\WebsocketServer;

class SwooleCommand
{

    /**
     * Run swoole http server
     *
     * @param string $app Running app
     * @throws \yii\base\InvalidConfigException
     */
    public static function Http($app, $d = 0)
    {
        if (!isset($app)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    Yii::$app->params['swoole']['web']['server']['daemonize'] = $d;
                    HttpServer::getInstance(Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function Websocket($app, $d = 0)
    {
        if (!isset($app)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    Yii::$app->params['swoole']['web']['server']['daemonize'] = $d;
                    WebsocketServer::getInstance(Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function Tcp($app, $d = 0)
    {
        if (!isset($app)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    Yii::$app->params['swoole']['tcp']['server']['daemonize'] = $d;
                    RpcServer::getInstance(Yii::$app->params['swoole']);
                    break;
                case 'stop':
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function Task($app, $d = 0)
    {
        if (!isset($app)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    Yii::$app->params['swoole']['task']['server']['daemonize'] = $d;
                    TaskServer::getInstance(Yii::$app->params['swoole']);
                    break;
                case 'stop':
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function Process($app, $work)
    {
        if (!isset($app) || !isset($work)) {
            exit("No argv.\n");
        } else {
            $work = $work && Yii::$app->get($work, false) ? Yii::$app->{$work} : new $work();
            switch ($app) {
                case 'start':
                    ProcessServer::getInstance()->start($work);
                    break;
                case 'stop':
                    ProcessServer::getInstance()->stop($work);
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function ProcessPool($app, $name)
    {
        if (!isset($app) || !isset($name)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    if (isset(Yii::$app->processPool[$name])) {
                        Yii::createObject(Yii::$app->processPool[$name])->start();
                    }
                    break;
                case 'stop':
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

}

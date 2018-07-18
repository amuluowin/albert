<?php

namespace yii\swoole\commands;

use Yii;
use yii\swoole\process\BaseProcess;
use yii\swoole\server\HttpServer;
use yii\swoole\server\ProcessServer;
use yii\swoole\server\RpcServer;
use yii\swoole\server\TaskServer;
use yii\swoole\udp\UdpServer;
use yii\swoole\websocket\WebsocketServer;

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
                    HttpServer::getInstance('web', Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    Yii::$app->params['swoole']['web']['server']['daemonize'] = $d;
                    HttpServer::getInstance('web', Yii::$app->params['swoole'])->stop();
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
                    WebsocketServer::getInstance('web', Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    Yii::$app->params['swoole']['web']['server']['daemonize'] = $d;
                    WebsocketServer::getInstance('web', Yii::$app->params['swoole'])->stop();
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function Rpc($app, $d = 0)
    {
        if (!isset($app)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    Yii::$app->params['swoole']['rpc']['server']['daemonize'] = $d;
                    RpcServer::getInstance('rpc', Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    Yii::$app->params['swoole']['rpc']['server']['daemonize'] = $d;
                    RpcServer::getInstance('rpc', Yii::$app->params['swoole'])->stop();
                    break;
                case 'restart':
                    break;
                default:
                    exit("Not support this argv.\n");
                    break;
            }
        }
    }

    public static function Udp($app, $d = 0)
    {
        if (!isset($app)) {
            exit("No argv.\n");
        } else {
            switch ($app) {
                case 'start':
                    Yii::$app->params['swoole']['udp']['server']['daemonize'] = $d;
                    UdpServer::getInstance('udp', Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    Yii::$app->params['swoole']['udp']['server']['daemonize'] = $d;
                    UdpServer::getInstance('udp', Yii::$app->params['swoole'])->stop();
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
                    TaskServer::getInstance('task', Yii::$app->params['swoole'])->start();
                    break;
                case 'stop':
                    Yii::$app->params['swoole']['task']['server']['daemonize'] = $d;
                    TaskServer::getInstance('task', Yii::$app->params['swoole'])->stop();
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
            /**
             * @var BaseProcess $work
             */
            $worker = $work && isset(Yii::$app->process[$work]) ? Yii::createObject(Yii::$app->process[$work]) : new $work();
            $worker->name = $work;
            switch ($app) {
                case 'start':
                    ProcessServer::getInstance()->start($worker);
                    break;
                case 'stop':
                    ProcessServer::getInstance()->stop($worker);
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

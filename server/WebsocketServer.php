<?php

namespace yii\swoole\server;

use swoole_http_request;
use swoole_http_response;
use swoole_server;
use swoole_table;
use swoole_websocket_frame;
use swoole_websocket_server;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * WebSocket服务器
 *
 * @package yii\swoole\server
 */
class WebsocketServer extends HttpServer
{
    private static $instance;

    private $wsUser;

    protected function createServer()
    {
        $this->server = new swoole_websocket_server($this->config['host'], $this->config['port'], $this->config['type']);
    }

    function onOpen($server, $request)
    {
        if (isset($this->config['wsUser']) && !$this->wsUser) {
            $this->wsUser = Yii::createObject($this->config['wsUser']);
        } else {
            $server->close($request->fd);
            return;
        }

        if (!$this->wsUser->handShake($server, $request)) {
            $server->close($request->fd);
            return;
        }
    }

    public function onMessage(swoole_server $server, swoole_websocket_frame $frame)
    {
        $result = ['status' => 503, 'code' => 0, 'message' => '传递参数有误', 'data' => []];

        $data = json_decode($frame->data, true);
        if (($cmd = ArrayHelper::getValue($data, 'cmd')) === null) {
            $server->push($frame->fd, json_encode($result));
        } else {
            //准备工作
            $request = Yii::$app->getRequest();
            $response = Yii::$app->getResponse();

            $query = ArrayHelper::getValue($data, 'query', []);
            $body = ArrayHelper::getValue($data, 'data', []);
            $to = ArrayHelper::getValue($data, 'sendto');

            $request->setHeaders(Yii::$app->cache->get('websocketheaders'));
            Yii::$app->beforeRun();
            try {
                //判断转发RPC
                $route = substr($cmd, 0, strrpos($cmd, '/'));
                if (!in_array($route, Yii::$rpcList)
                    || in_array($route, ArrayHelper::getValue(Yii::$app->params, 'rpcCoR', []))
                ) {
                    $response->data = Yii::$app->rpc->send([$cmd, [$query, $body]])->recv();
                    $response->format = 'json';
                } else {
                    $request->setBodyParams($body);
                    $request->setHostInfo(null);
                    $request->setUrl($cmd);
                    $request->setRawBody(json_encode($body));
                    $request->setQueryParams($query);
                    $response->data = Yii::$app->runAction($cmd, $query);
                }
            } catch (\Exception $e) {
                Yii::error($e->getMessage());
            } finally {
                $response->websocketPrepare();
                $server->push($to ? Yii::$app->usercache->get('wsclient:' . $to)['fd'] : $frame->fd, $response->content);
                Yii::getLogger()->flush(true);
                Yii::$app->release();
            }
        }
    }

    public function onClose($server, $fd, $from_id)
    {
        parent::onClose($server, $fd, $from_id);
        Yii::info("client {$fd} closed\n", __METHOD__);
        Yii::getLogger()->flush(true);
    }


    public static function getInstance($config)
    {
        if (!self::$instance) {
            self::$instance = new WebsocketServer($config);
        }
        return self::$instance;
    }

}

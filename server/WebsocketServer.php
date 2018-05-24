<?php

namespace yii\swoole\server;

use swoole_server;
use swoole_websocket_frame;
use swoole_websocket_server;
use Yii;
use yii\helpers\ArrayHelper;
use yii\swoole\base\SingletonTrait;
use yii\swoole\web\WsAuthInterface;
use yii\swoole\web\WsSendInterface;

/**
 * WebSocket服务器
 *
 * @package yii\swoole\server
 */
class WebsocketServer extends HttpServer
{
    use SingletonTrait;
    /**
     * @var WsAuthInterface
     */
    private $wsAuth;

    /**
     * @var WsSendInterface
     */
    private $wsSend;

    protected function createServer()
    {
        $this->server = new swoole_websocket_server($this->config['host'], $this->config['port'], $this->config['type']);
    }

    function onOpen($server, $request)
    {
        if (isset($this->config['wsAuth']) && !$this->wsAuth) {
            $this->wsAuth = Yii::createObject($this->config['wsAuth']);
        } else {
            $server->close($request->fd);
            return;
        }

        if (!$this->wsAuth->handShake($server, $request)) {
            $server->close($request->fd);
            return;
        }

        if (isset($this->config['wsSend']) && !$this->wsSend) {
            $this->wsSend = Yii::createObject($this->config['wsSend']);
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

            Yii::$app->beforeRun();
            try {
                //判断转发RPC
                $route = substr($cmd, 0, strrpos($cmd, '/'));
                if (!in_array($route, Yii::$rpcList)
                    || in_array($route, ArrayHelper::getValue(Yii::$app->rpc, 'remoteList', []))
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
                if (is_array($response->data) && isset($response->data['type'])) {
                    $this->wsSend->{$response->data['type']}($server, $response->content, $to ? Yii::$app->usercache->get('wsclient:' . $to)['fd'] : $frame->fd);
                } else {
                    $this->wsSend->send($server, $response->content, $to ? Yii::$app->usercache->get('wsclient:' . $to)['fd'] : $frame->fd);
                }
                Yii::getLogger()->flush(true);
                Yii::$app->release();
            }
        }
    }

    public function onClose($server, $fd, $from_id)
    {
        parent::onClose($server, $fd, $from_id);
    }
}

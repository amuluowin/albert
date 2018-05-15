<?php

namespace yii\swoole\web;

use Yii;
use yii\base\ErrorException;
use yii\base\InvalidRouteException;
use yii\filters\ContentNegotiator;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\helpers\CoroHelper;

trait HttpTrait
{
    /**
     * 执行请求
     *
     * @param swoole_http_request $request
     * @param swoole_http_response $response
     */
    public function onRequest($request, $response)
    {
        $file = $this->root . '/' . $this->indexFile;
        $id = CoroHelper::getId();

        $_GET[$id] = isset($request->get) ? $request->get : [];
        $_POST[$id] = isset($request->post) ? $request->post : [];
        $_FILES[$id] = isset($request->files) ? $request->files : [];
        $_COOKIE[$id] = isset($request->cookie) ? $request->cookie : [];

        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['DOCUMENT_ROOT'] = $this->root;
        $_SERVER['DOCUMENT_URI'] = $_SERVER['SCRIPT_NAME'] = '/' . $this->indexFile;

        $this->server->currentSwooleRequest[$id] = $request;
        $this->server->currentSwooleResponse[$id] = $response;
        try {
            Yii::$app->beforeRun();
            //判断转发RPC
            $service = explode('/', ltrim($request->server['request_uri'], '/'));
            if (count($service) !== 3) {
                throw new InvalidRouteException();
            }
            list($service, $route, $method) = $service;
            if (!in_array($route, Yii::$rpcList[$service])
                || in_array($route, ArrayHelper::getValue(Yii::$app->rpc->remoteList, $service, []))
            ) {
                $appResponse = Yii::$app->getResponse();
                $appResponse->data = Yii::$app->rpc->create($service, $route)->$method([Yii::$app->getRequest()->getQueryParams(), Yii::$app->getRequest()->getBodyParams()])->recv();
                $filter = new ContentNegotiator(['formats' => [
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml' => Response::FORMAT_XML,
                ]]);
                $filter->bootstrap(Yii::$app);
                Yii::$app->end();
            } else {
                Yii::$app->run();
            }
        } catch (ErrorException $e) {
            Yii::$app->getErrorHandler()->handleException($e);
        } catch (\Exception $e) {
            Yii::$app->getErrorHandler()->handleException($e);
        } finally {
            //结束
            Yii::getLogger()->flush(true);
            Yii::$app->release();
        }
    }
}
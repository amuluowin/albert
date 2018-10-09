<?php

namespace yii\swoole\web;

use Yii;
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
        $id = CoroHelper::getId();

        $_GET[$id] = isset($request->get) ? $request->get : [];
        $_POST[$id] = isset($request->post) ? $request->post : [];
        $_FILES[$id] = isset($request->files) ? $request->files : [];
        $_COOKIE[$id] = isset($request->cookie) ? $request->cookie : [];

        $this->server->currentSwooleRequest[$id] = $request;
        $this->server->currentSwooleResponse[$id] = $response;
        try {
            Yii::$app->beforeRun();
            Yii::$app->run();
        } catch (\Swoole\ExitException $e) {

        } catch (\Exception $e) {
            /**
             * @var ErrorHandler
             */
            $errorHandle = Yii::$app->getErrorHandler();
            $errorHandle->handleException($e);
        } finally {
            //结束
            Yii::getLogger()->flush(true);
            Yii::$app->release($id);
        }
    }
}
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
            Yii::$app->run();
        } catch (\Exception $e) {
            /**
             * @var ErrorHandler
             */
            $errorHandle = Yii::$app->getErrorHandler();
            $errorHandle->handleException($e);
        } finally {
            //结束
            Yii::getLogger()->flush(true);
            Yii::$app->release();
        }
    }
}
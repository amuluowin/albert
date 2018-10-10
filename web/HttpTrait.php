<?php

namespace yii\swoole\web;

use Yii;

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
        try {
            Yii::$app->getRequest()->setSwooleRequest($request);
            Yii::$app->getResponse()->setSwooleResponse($response);
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
            Yii::$app->release();
        }
    }
}
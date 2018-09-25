<?php

namespace yii\swoole\debug\remotedebug;

use Yii;

/**
 * Class RequestPanel
 *
 * @package yii\swoole\debug\remotedebug
 */
class RequestPanel extends \yii\debug\panels\RequestPanel
{
    public function save()
    {
        $headers = Yii::$app->getRequest()->getHeaders();
        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            if (is_array($value) && count($value) == 1) {
                $requestHeaders[$name] = current($value);
            } else {
                $requestHeaders[$name] = $value;
            }
        }

        $responseHeaders = [];
        foreach (headers_list() as $header) {
            if (($pos = strpos($header, ':')) !== false) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));
                if (isset($responseHeaders[$name])) {
                    if (!is_array($responseHeaders[$name])) {
                        $responseHeaders[$name] = [$responseHeaders[$name], $value];
                    } else {
                        $responseHeaders[$name][] = $value;
                    }
                } else {
                    $responseHeaders[$name] = $value;
                }
            } else {
                $responseHeaders[] = $header;
            }
        }
        if (Yii::$app->requestedAction) {
            if (Yii::$app->requestedAction instanceof InlineAction) {
                $action = get_class(Yii::$app->requestedAction->controller) . '::' . Yii::$app->requestedAction->actionMethod . '()';
            } else {
                $action = get_class(Yii::$app->requestedAction) . '::run()';
            }
        } else {
            $action = null;
        }

        $data = [
            'flashes' => $this->getFlashes(),
            'statusCode' => Yii::$app->getResponse()->getStatusCode(),
            'requestHeaders' => $requestHeaders,
            'responseHeaders' => $responseHeaders,
            'route' => Yii::$app->requestedAction ? Yii::$app->requestedAction->getUniqueId() : Yii::$app->requestedRoute,
            'action' => $action,
            'actionParams' => Yii::$app->requestedParams,
            'general' => [
                'method' => Yii::$app->getRequest()->getMethod(),
                'isAjax' => Yii::$app->getRequest()->getIsAjax(),
                'isPjax' => Yii::$app->getRequest()->getIsPjax(),
                'isFlash' => Yii::$app->getRequest()->getIsFlash(),
                'isSecureConnection' => Yii::$app->getRequest()->getIsSecureConnection(),
            ],
            'requestBody' => Yii::$app->getRequest()->getRawBody() == '' ? [] : [
                'Content Type' => Yii::$app->getRequest()->getContentType(),
                'Raw' => Yii::$app->getRequest()->getRawBody(),
                'Decoded to Params' => Yii::$app->getRequest()->getBodyParams(),
            ],
            'responseBody' => Yii::$app->getResponse()->data == '' ? [] : [
                'Raw' => Yii::$app->getResponse()->data,
                'Decoded to Params' => Yii::$app->getResponse()->content,
            ],
        ];

        foreach ($this->displayVars as $name) {
            $data[trim($name, '_')] = empty($GLOBALS[$name]) ? [] : $GLOBALS[$name];
        }

        return $data;
    }
}

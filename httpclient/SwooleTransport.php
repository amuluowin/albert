<?php

namespace yii\swoole\httpclient;

use addons\wechat\support\Arr;
use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class SwooleTransport extends \yii\httpclient\Transport
{

    /**
     * @inheritdoc
     */
    public function send($request)
    {
        $request->beforeSend();

        $request->prepare();

        $url = $request->getFullUrl();
        $urlarr = parse_url($url);
        $method = strtoupper($request->getMethod());

        $content = $request->getContent();

        $headers = $request->composeHeaderLines();

        $options = $request->getOptions();

        $token = $request->client->createRequestLogToken($method, $url, $headers, $content);
        Yii::info($token, __METHOD__);
        Yii::beginProfile($token, __METHOD__);

        try {
            $config = [
                'hostname' => $urlarr['host'],
                'port' => isset($urlarr['port']) ? $urlarr['port'] : ($urlarr['scheme'] === 'http' ? 80 : 443),
                'scheme' => $urlarr['scheme'],
                'timeout' => $request->dns_timeout,
                'pool_size' => $request->pool_size,
                'busy_size' => $request->busy_size
            ];
            if (($ret = filter_var($config['hostname'], FILTER_VALIDATE_IP) ? $config['hostname'] : null) || ($ret = swoole_async_dns_lookup_coro($config['hostname'], $config['timeout']))) {
                $cli = new \Swoole\Coroutine\Http\Client($ret, $config['port'], $config['scheme'] === 'https' ? true : false);
                if ($cli->errCode !== 0) {
                    throw new ServerErrorHttpException("Can not connect to " . $config['hostname'] . ':' . $config['port']);
                }
            } else {
                throw new ServerErrorHttpException("Can not connect to " . $config['hostname'] . ':' . $config['port']);
            }
            //headers
            $headers = $request->getHeaders();
            $sendHeaders = [];
            foreach ($headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                // set replace for first occurrence of header but false afterwards to allow multiple
                foreach ($values as $value) {
                    $sendHeaders[$name] = $value;
                }
            }
            //cookies
            $cookies = $request->getCookies();
            $sendCookies = [];
            foreach ($cookies as $cookie) {
                $value = $cookie->value;
                if ($cookie->expire != 1 && isset($validationKey)) {
                    $value = Yii::$app->getSecurity()->hashData(serialize([$cookie->name, $value]), $validationKey);
                }
                $sendCookies[$cookie->name] = $value;
            }

            $cli->setHeaders($sendHeaders);
            $cli->setCookies($sendCookies);
            $cli->set(ArrayHelper::merge([
                'timeout' => isset($options['timeout']) ? $options['timeout'] : $request->client_timeout,
                'keep_alive' => $request->keep_alive,
                'websocket_mask' => $request->websocket_mask,
                'ssl_cert_file'
            ], array_filter([
                'ssl_cert_file' => ArrayHelper::getValue($options, 'sslLocalCert'),
                'ssl_key_file' => ArrayHelper::getValue($options, 'sslLocalPk')
            ])));
            $cli->setMethod($method);
            if (strtolower($method) !== 'get') {
                $cli->setData($content);
            }
            $cli->setDefer();
            if (strtolower($method) === 'get') {
                $cli->execute(isset($urlarr['path']) ? $urlarr['path'] . '?' . $content : '/');
            } else {
                $cli->execute(isset($urlarr['path']) ? $urlarr['path'] : '/');
            }
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }

        Yii::endProfile($token, __METHOD__);
        $response = $request->client->createConn($cli);
        $request->afterSend($response);

        return $response;
    }

}

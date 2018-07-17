<?php

namespace yii\swoole\httpclient;

use Yii;
use yii\httpclient\Exception;
use yii\swoole\helpers\ArrayHelper;

class SwooleTransport extends \yii\httpclient\Transport
{
    protected $reconnect = 3;
    protected $curconnect = 0;

    /**
     * Performs given request.
     * @param Request $request request to be sent.
     * @return Response response instance.
     * @throws Exception on failure.
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
            $cli = $this->getConn($urlarr, $request);
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
                'timeout' => isset($options['timeout']) ? $options['timeout'] : $request->timeout,
                'keep_alive' => $request->keep_alive,
                'websocket_mask' => $request->websocket_mask,
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
            $response = $request->client->createConn(isset($cli) ? $cli : null);
            return $response;
        }

        Yii::endProfile($token, __METHOD__);
        $response = $request->client->createConn($cli);
        $request->afterSend($response);

        return $response;
    }

    protected function getConn(array $urlarr, Request $request)
    {
        $port = isset($urlarr['port']) ? $urlarr['port'] : ($urlarr['scheme'] === 'http' ? 80 : 443);
        $conn = new \Swoole\Coroutine\Http\Client($urlarr['host'], $port, $urlarr['scheme'] === 'https' ? true : false);
        if ($conn->errCode !== 0) {
            if ($this->reconnect <= $this->curconnect) {
                $this->curconnect = 0;
                $conn->close();
                throw new Exception(sprintf('connect to %s:%d error:', $config['hostname'], $config['port'], $conn->error));
            } else {
                $this->curconnect++;
                $conn->close();
                $conn = $this->getConn($urlarr, $request);
            }
        }
        return $conn;
    }

}

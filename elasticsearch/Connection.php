<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/20
 * Time: 18:34
 */

namespace yii\swoole\elasticsearch;

use Yii;
use yii\base\InvalidConfigException;
use yii\elasticsearch\Exception;
use yii\swoole\httpclient\Client;

class Connection extends \yii\elasticsearch\Connection
{
    /**
     * Performs HTTP request
     *
     * @param string $method method name
     * @param string $url URL
     * @param string $requestBody request body
     * @param bool $raw if response body contains JSON and should be decoded
     * @return mixed if request failed
     * @throws Exception if request failed
     * @throws InvalidConfigException
     */
    protected function httpRequest($method, $url, $requestBody = null, $raw = false)
    {
        $method = strtoupper($method);
        $headers = [
            'UserAgent' => 'Yii Framework ' . Yii::getVersion() . ' ' . __CLASS__,
            'Content-Type' => 'application/json'
        ];

        if (!empty($this->auth) || isset($this->nodes[$this->activeNode]['auth']) && $this->nodes[$this->activeNode]['auth'] !== false) {
            $auth = isset($this->nodes[$this->activeNode]['auth']) ? $this->nodes[$this->activeNode]['auth'] : $this->auth;
            if (empty($auth['username'])) {
                throw new InvalidConfigException('Username is required to use authentication');
            }
            if (empty($auth['password'])) {
                throw new InvalidConfigException('Password is required to use authentication');
            }

            $headers['Authorization'] = base64_decode($auth['username'] . ':' . $auth['password'])
        }

        $requestConfig = [];

        if ($this->connectionTimeout !== null) {
            $requestConfig['dns_timeout'] = $this->connectionTimeout;
        }
        if ($this->dataTimeout !== null) {
            $requestConfig['dns_timeout'] = $this->dataTimeout;
        }
        if ($method == 'HEAD') {
            $requestBody = [];
        }

        if (is_array($url)) {
            list($protocol, $host, $q) = $url;
            if (strncmp($host, 'inet[', 5) == 0) {
                $host = substr($host, 5, -1);
                if (($pos = strpos($host, '/')) !== false) {
                    $host = substr($host, $pos + 1);
                }
            }
            $profile = "$method $q#$requestBody";
            $url = "$protocol://$host/$q";
        } else {
            $profile = false;
        }

        Yii::trace("Sending request to elasticsearch node: $method $url\n$requestBody", __METHOD__);
        if ($profile !== false) {
            Yii::beginProfile($profile, __METHOD__);
        }
        $responese = (new Client(['requestConfig' => [
            'dns_timeout' => 1,
            'client_timeout' => 1
        ]]))->createRequest()->setUrl($url)->setMethod($method)->setData($requestBody)->setHeaders($headers)->send();
        $body = $responese->getData();
        if (!$responese->getIsOk()) {
            throw new Exception('Elasticsearch request failed: ' . $responese->getConn()->errno . ' - ' . $responese->getConn()->error, [
                'requestMethod' => $method,
                'requestUrl' => $url,
                'requestBody' => $requestBody,
                'responseHeaders' => $headers,
                'responseBody' => $this->decodeErrorBody($body),
            ]);
        }


        $responseCode = $responese->getStatusCode();

        if ($profile !== false) {
            Yii::endProfile($profile, __METHOD__);
        }

        if ($responseCode >= 200 && $responseCode < 300) {
            if ($method == 'HEAD') {
                return true;
            } else {
                return $body;
            }
        } elseif ($responseCode == 404) {
            return false;
        } else {
            throw new Exception("Elasticsearch request failed with code $responseCode.", [
                'requestMethod' => $method,
                'requestUrl' => $url,
                'requestBody' => $requestBody,
                'responseCode' => $responseCode,
                'responseHeaders' => $headers,
                'responseBody' => $this->decodeErrorBody($body),
            ]);
        }
    }
}
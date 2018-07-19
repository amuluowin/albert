<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/17
 * Time: 10:51
 */

namespace yii\swoole\rpc;

use Yii;
use yii\httpclient\Response;
use yii\swoole\governance\provider\ProviderInterface;
use yii\swoole\httpclient\Client;

class HttpClient extends IRpcClient
{
    /**
     * @var int
     */
    public $maxPoolSize = 100;

    /**
     * @var int
     */
    public $busy_pool = 50;
    /**
     * @var Response
     */
    public $client;

    /**
     * @var float
     */
    public $timeout = -1;

    /**
     * @var array
     */
    public $setting = [];

    public function recv()
    {
        $result = $this->client->getData();
        Yii::$app->rpc->afterRecv($result);
        $this->release();
        return $result;
    }

    public function __call($name, $params)
    {
        $data = [];
        list($data['service'], $data['route']) = Yii::$app->rpc->getService();
        /**
         * @var ProviderInterface $provider
         */
        $provider = Yii::$app->gr->provider;
        $server = $provider->getServices($data['service'], $provider->apiPrefix);
        list($server, $port) = Yii::$app->gr->balance->select($data['service'])->getCurrentService($server);
        $url = 'http://' . $server . ':' . $port . $data['service'] . $data['route'];
        $data['method'] = $name;
        $data['params'] = array_shift($params);
        $data['fastCall'] = Yii::$app->rpc->fastCall;
        $header = [
            'Authorization' => Yii::$app->request->getHeaders()->get('Authorization'),
        ];
        $this->client = (new Client())->createRequest()->setHeaders(array_filter($header))->setMethod($data['method'])
            ->setUrl($url)->setData($data['params'])->send();
        $data = Yii::$app->rpc->beforeSend($data);
        if ($this->IsDefer) {
            $this->IsDefer = false;
            return $this;
        }
        return $this->recv();
    }
}
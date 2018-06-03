<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-02
 * Time: 22:37
 */

namespace yii\swoole\consul;

use Yii;
use yii\base\Component;
use yii\swoole\httpclient\Client;

class ConsulClient extends Component
{
    /**
     * @var string
     */
    public $address = "http://127.0.0.1";

    /**
     * @var int
     */
    public $port = 8500;

    /**
     * @var Client
     */
    public $httpClient;

    public function init()
    {
        if (!$this->httpClient instanceof Client) {
            $this->httpClient = Yii::$app->httpclient;
        }
    }
}
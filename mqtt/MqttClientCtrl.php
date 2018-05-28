<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-25
 * Time: 下午4:28
 */

namespace yii\swoole\mqtt;


use yii\base\Component;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\mqtt\log\MqttLogInterface;
use yii\swoole\mqtt\store\TmpStorageInterface;

class MqttClientCtrl extends Component
{
    /**
     * @var string
     */
    public $host = '127.0.0.1';
    /**
     * @var int
     */
    public $port = 1883;
    /**
     * @var string
     */
    public $username = 'admin';
    /**
     * @var string
     */
    public $password = 'public';

    /**
     * @var array
     */
    private $clients = [];

    /**
     * @var MqttLogInterface
     */
    public $logger;

    /**
     * @var TmpStorageInterface
     */
    public $store;

    public function connect(int $client_id, array $topics = []): MqttClient
    {
        if (isset($this->clients[$client_id])) {
            $r = $this->clients[$client_id];
        } else {
            $r = new MqttClient($this->host, $this->port, $client_id);
            $this->clients[$client_id] = $r;

            $r->setAuth($this->username, $this->password);
            $r->setKeepAlive(60);
            $r->setLogger($this->logger);
            $r->setStore($this->store);
            $r->setTopics($topics);
            $r->connect();
        }
        return $r;
    }

    public function subscribe(int $client_id, array $topics, bool $clear = false): MqttClient
    {
        $r = $this->connect($client_id);
        if ($clear) {
            $r->setTopics($topics);
        } else {
            $r->setTopics(ArrayHelper::merge($r->getTopics(), $topics));
        }
        $r->subscribe();
        return $r;
    }

    public function unsubscribe(int $client_id, array $topics): MqttClient
    {
        $r = $this->connect($client_id);
        $r->unsubscribe($topics);
        return $r;
    }

    public function publish(int $client_id, array $message): MqttClient
    {
        $r = $this->connect($client_id);
        foreach ($message as $m) {
            list($route, $content, $qos) = $m;
            $r->publish($route, $content, $qos);
        }
        return $r;
    }
}
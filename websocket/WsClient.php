<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/9
 * Time: 14:37
 */

namespace yii\swoole\websocket;

use Yii;
use yii\base\Component;
use yii\swoole\base\Defer;
use yii\swoole\coroutine\BaseClient;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\pool\HttpPool;
use yii\web\ServerErrorHttpException;

class WsClient extends Component implements ICoroutine
{
    use Defer;
    /**
     * @var float
     */
    public $timeout = 3;

    /**
     * @var array
     */
    public $client;

    /**
     * @var int
     */
    public $maxPoolSize = 50;
    /**
     * @var int
     */
    public $busy_pool = 30;


    public function recv(float $timeout = 0)
    {
        $result = $this->client->recv($timeout ?: $this->timeout);
        $this->release();
        return $result;
    }

    public function connect(string $uri, int $port)
    {
        $key = sprintf('ws:%s:%d', $uri, $port);
        if (!Yii::$container->hasSingleton('wsclient')) {
            Yii::$container->setSingleton('wsclient', [
                'class' => HttpPool::class
            ]);
        }
        if (($this->client = Yii::$container->get('wsclient')->fetch($key)) === null) {
            $this->client = Yii::$container->get('wsclient')->create($key,
                [
                    'hostname' => $uri,
                    'port' => $port,
                    'timeout' => $this->timeout,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])
                ->fetch($key);
        }
        return $this;
    }

    public function handShake(array $params)
    {
        $route = array_shift($params);
        $this->client->setHeaders($params);
        $result = ($this->client->upgrade($route) && $this->client->statusCode === 101);
        return $result;
    }

    public function send(string $data)
    {
        if ($this->client->push($data)) {
            if ($this->IsDefer) {
                $this->IsDefer = false;
                return clone $this;
            }
            return $this->recv();
        }

        throw new ServerErrorHttpException(sprintf('can not send data to %s', $key));
    }

    public function release()
    {
        if (Yii::$container->hasSingleton('wsclient') && $this->client) {
            Yii::$container->get('wsclient')->recycle($this->client);
            $this->client = null;
        }
    }
}
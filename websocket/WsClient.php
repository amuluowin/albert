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
    private $client;

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

    public function send(string $uri, int $port, string $route, array $data)
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

        $this->client->setHeaders([
            'UserAgent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.75 Safari/537.36'
        ]);
        if ($this->client->upgrade($route)) {
            if ($this->client->push(json_encode($data))) {
                if ($this->IsDefer) {
                    $this->IsDefer = false;
                    return clone $this;
                }
                return $this->recv();
            }
        }
        throw new ServerErrorHttpException(sprintf('can not send data to %s', $key));
    }

    public function release()
    {
        if (Yii::$container->hasSingleton('wsclient') && $this->client) {
            Yii::$container->get('wsclient')->recycle($this->client);
            unset($this->client);
        }
    }
}
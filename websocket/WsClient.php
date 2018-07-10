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
use yii\swoole\helpers\CoroHelper;
use yii\swoole\pool\HttpPool;

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
    private $client = [];

    /**
     * @var int
     */
    public $maxPoolSize = 50;
    /**
     * @var int
     */
    public $busy_pool = 30;

    public function getClient(): ?\Swoole\Coroutine\Http\Client
    {
        $id = CoroHelper::getId();
        return isset($this->client[$id]) ? $this->client[$id] : null;
    }

    public function setClient($value)
    {
        $id = CoroHelper::getId();
        $this->client[$id] = $value;
    }

    public function recv()
    {
        $result = $this->getClient()->recv($this->timeout);
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
        if (($conn = Yii::$container->get('wsclient')->fetch($key)) === null) {
            $conn = Yii::$container->get('wsclient')->create($key,
                [
                    'hostname' => $uri,
                    'port' => $port,
                    'timeout' => $this->timeout,
                    'pool_size' => $this->maxPoolSize,
                    'busy_size' => $this->busy_pool
                ])
                ->fetch($key);
        }

        $conn->set([
            'websocket_mask' => true,
        ]);
        $conn->setHeaders([
            'UserAgent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.75 Safari/537.36'
        ]);
        $conn->upgrade($route);
        $this->setClient($conn);
        $this->getClient()->push(json_encode($data));
        if ($this->IsDefer) {
            $this->IsDefer = false;
            return $this;
        }
        return $this->recv();
    }

    public function release()
    {
        $id = CoroHelper::getId();
        if (Yii::$container->hasSingleton('wsclient') && isset($this->client[$id])) {
            Yii::$container->get('wsclient')->recycle($this->client[$id]);
            unset($this->client[$id]);
            unset($this->defer[$id]);
        }
    }
}
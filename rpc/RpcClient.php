<?php

namespace yii\swoole\rpc;

use Yii;
use yii\base\Component;
use yii\swoole\base\Defer;
use yii\swoole\helpers\CoroHelper;

class RpcClient extends Component
{
    use Defer;
    /**
     * @var IRpcClient
     */
    public $config_r;

    /**
     * @var IRpcClient
     */
    public $config_n;

    /**
     * @var array
     */
    private $service = [];

    /**
     * @var string
     */
    private $traceId;

    /**
     * @var bool
     */
    public $fastCall = false;

    public function getService(): array
    {
        return isset($this->service[CoroHelper::getId()]) ? $this->service[CoroHelper::getId()] : [null, null];
    }

    public function call(string $ser, string $route)
    {
        $this->service[CoroHelper::getId()] = [$ser, $route];
        return $this;
    }

    public function beforeSend(array $data): array
    {
        if (Yii::$app->gr->tracer && Yii::$app->gr->tracer->isTrace) {
            $this->traceId = Yii::$app->request->getTraceId();
            $data = Yii::$app->gr->tracer->getCollect($this->traceId, $data);
        }
        return $data;
    }

    public function afterRecv($result)
    {
        if (Yii::$app->gr->tracer && Yii::$app->gr->tracer->isTrace) {
            $data = [];
            $data['result'] = $result;
            Yii::$app->gr->tracer->addCollect($this->traceId, $data);
        }
    }

    public function __call($name, $params)
    {
        list($ser, $route) = $this->getService();
        if (!isset(Yii::$rpcList) || (key_exists($ser, Yii::$rpcList) && in_array($route, Yii::$rpcList[$ser]))) {
            $client = is_array($this->config_n) ? clone Yii::createObject($this->config_n) : clone $this->config_n;
        } else {
            $client = is_array($this->config_r) ? clone Yii::createObject($this->config_r) : clone $this->config_r;
        }
        /**
         * @var IRpcClient $client
         */
        if ($this->IsDefer) {
            $client->IsDefer = $this->IsDefer;
            return $this;
        }
        return $client->$name(...$params);
    }
}

<?php

namespace yii\swoole\rpc;

use Yii;
use yii\base\Component;
use yii\swoole\base\Defer;
use yii\swoole\governance\Governance;
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
     * @var IRpcClient
     */
    public $config_h;

    /**
     * @var array
     */
    private $service = [];

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
        /**
         * @var Governance $gc
         */
        $gc = Yii::$app->gr;
        if ($gc->tracer && $gc->tracer->isTrace) {
            $data = $gc->tracer->getCollect(Yii::$app->request->getTraceId(), $data);
        }
        return $data;
    }

    public function afterRecv($result)
    {
        /**
         * @var Governance $gc
         */
        $gc = Yii::$app->gr;
        if ($gc->tracer && $gc->tracer->isTrace) {
            $data = [];
            $data['result'] = $result;
            $gc->tracer->addCollect(Yii::$app->request->getTraceId(), $data);
            $gc->tracer->flushCollect(Yii::$app->request->getTraceId());
        }
    }

    public function __call($name, $params)
    {
        list($ser, $route) = $this->getService();
        if (!isset(Yii::$rpcList) || (key_exists($ser, Yii::$rpcList) && in_array($route, Yii::$rpcList[$ser]))) {
            $client = is_array($this->config_n) ? clone Yii::createObject($this->config_n) : clone $this->config_n;
        } elseif (strpos($ser, '/') === 0) {
            $client = is_array($this->config_h) ? clone Yii::createObject($this->config_h) : clone $this->config_h;
        } else {
            $client = is_array($this->config_r) ? clone Yii::createObject($this->config_r) : clone $this->config_r;
        }
        /**
         * @var IRpcClient $client
         */
        if ($this->IsDefer) {
            $client->IsDefer = $this->IsDefer;
        }
        return $client->$name(...$params);
    }
}

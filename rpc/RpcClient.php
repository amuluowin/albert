<?php

namespace yii\swoole\rpc;

use Yii;
use yii\base\Component;
use yii\swoole\governance\trace\TraceInterface;
use yii\swoole\helpers\CoroHelper;

class RpcClient extends Component
{
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
    public $remoteList = [];

    /**
     * @var array
     */
    public $selfList = [];

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

    public function create(string $ser, string $route)
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
        if (key_exists($ser, Yii::$rpcList) && in_array($route, Yii::$rpcList[$ser])
            && !empty($this->remoteList)
            && !(key_exists($ser, $this->remoteList) && in_array($route, $this->remoteList[$ser]))) {
            $client = clone $this->config_n;
        } else {
            $client = clone $this->config_r;
        }
        return $client->$name(...$params);
    }
}

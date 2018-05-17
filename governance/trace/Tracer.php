<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-13
 * Time: 下午10:35
 */

namespace yii\swoole\governance\trace;

use Yii;
use yii\base\Component;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\governance\exporter\ExportInterface;
use yii\swoole\helpers\ArrayHelper;

class Tracer extends Component implements ICoroutine, TraceInterface
{
    /**
     * @var bool
     */
    public $isTrace = true;

    /**
     * @var array
     */
    public $collect = [];

    /**
     * @var ExportInterface
     */
    public $exporter;

    public function getCollect(string $traceId, array $collect):?array
    {
        if (isset($this->collect[$traceId])) {
            $this->collect[$traceId]['parentId'] = $this->collect[$traceId]['spanId'];
            $this->collect[$traceId]['spanId']++;
        } else {
            $this->collect[$traceId] = [
                'traceId' => $traceId,
                'spanId' => 0
            ];
        }
        $this->collect[$traceId]['sendTime'] = time();
        $this->collect[$traceId]['sendIp'] = current(swoole_get_local_ip());
        $this->collect[$traceId] = ArrayHelper::merge($this->collect[$traceId], $collect);
        return $this->collect[$traceId];
    }

    public function addCollect(string $traceId, array $collect)
    {
        $this->collect[$traceId] = ArrayHelper::merge($this->collect[$traceId], $collect);
    }

    public function release($traceId = null)
    {
        unset($this->collect[$traceId]);
    }
}
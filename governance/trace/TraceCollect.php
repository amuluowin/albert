<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-13
 * Time: 下午10:35
 */

namespace yii\swoole\governance\trace;

use yii\base\Component;
use yii\swoole\coroutine\ICoroutine;
use Yii;

class TraceCollect extends Component implements ICoroutine, TraceInterface
{
    private $collect = [];

    public function getCollect($traceId):?array
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
        return $this->collect[$traceId];
    }

    public function setCollect($traceId, array $collect)
    {
        $this->collect[$traceId] = $collect;
    }

    public function release($traceId = null)
    {
        unset($this->collect[$traceId]);
    }
}
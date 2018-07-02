<?php

namespace yii\swoole\httpclient;

use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\pool\HttpPool;

class PoolTransport extends SwooleTransport
{
    protected function getConn(array $urlarr, Request $request)
    {
        if (!Yii::$container->hasSingleton('httpclient')) {
            Yii::$container->setSingleton('httpclient', [
                'class' => HttpPool::class
            ]);
        }
        $port = isset($urlarr['port']) ? $urlarr['port'] : ($urlarr['scheme'] === 'http' ? 80 : 443);
        $key = sprintf('httpclient:%s:%s', $urlarr['host'], $port);
        if (($cli = Yii::$container->get('httpclient')->fetch($key)) === null) {
            $cli = Yii::$container->get('httpclient')->create($key,
                [
                    'hostname' => $urlarr['host'],
                    'port' => $port,
                    'timeout' => $request->timeout,
                    'pool_size' => $request->pool_size,
                    'busy_size' => $request->busy_size,
                    'scheme' => $urlarr['scheme']
                ])
                ->fetch($key);
        }
        return $cli;
    }
}

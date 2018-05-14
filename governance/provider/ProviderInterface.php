<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午9:26
 */

namespace yii\swoole\governance\provider;


interface ProviderInterface
{
    public function registerService(...$params);

    public function getServices(string $serviceName, string $tag = null);
}
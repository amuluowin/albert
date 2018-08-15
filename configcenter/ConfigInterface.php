<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-02
 * Time: 22:12
 */

namespace yii\swoole\configcenter;


use yii\swoole\httpclient\Client;

interface ConfigInterface
{
    public function putConfig(string $key, array $config, Client $client = null);

    public function delConfig(String $key, Client $client = null);

    public function getConfig(string $key, Client $client = null);
}
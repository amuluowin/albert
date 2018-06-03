<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-03
 * Time: 22:21
 */

namespace yii\swoole\configcenter;


interface SetConfig
{
    public function setConfig(string $confName, array $config);
}
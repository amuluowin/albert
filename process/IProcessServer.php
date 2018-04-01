<?php

namespace yii\swoole\process;

interface IProcessServer
{
    public function start($config, $work);

    public function stop($work);

    public static function getInstance();
}
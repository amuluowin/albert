<?php

namespace yii\swoole\process;

interface IProcessServer
{
    public function start($work);

    public function stop($work);

    public static function getInstance();
}
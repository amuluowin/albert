<?php

namespace yii\swoole\manager;

interface IServermanager {

    public function create();

    public function getlist($data = []);

    public function addserver($data);

    public function delserver($data = null);

    public function dealServer();

    public function getTable();
}

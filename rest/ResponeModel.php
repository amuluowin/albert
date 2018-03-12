<?php

namespace yii\swoole\rest;

use yii\base\BaseObject;

class ResponeModel extends BaseObject
{

    public $status;
    public $code;
    public $message;
    public $data;
    public $type;
    public $_meta;

    public function setModel($status, $code, $message, $data, $_meta = null)
    {
        $this->status = $status;
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
        $this->_meta = $_meta;
        return $this;
    }

}

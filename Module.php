<?php

namespace yii\swoole;

use Yii;
use yii\swoole\controllers\CreateCtrlTrait;

class Module extends \yii\base\Module
{
    use CreateCtrlTrait;

    /**
     * @var is debug this module
     */
    public $isService = true;

    public $logicNamespace;

    public $use_default_doc = true;

    public function init()
    {
        parent::init();
        if ($this->logicNamespace === null) {
            $class = get_class($this);
            if (($pos = strrpos($class, '\\')) !== false) {
                $this->logicNamespace = substr($class, 0, $pos) . '\\modellogic';
            }
        }
    }
}

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

    public $use_default_doc = true;
}

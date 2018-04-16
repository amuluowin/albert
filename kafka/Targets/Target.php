<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-4-14
 * Time: 下午8:44
 */

namespace yii\swoole\kafka\Targets;

use yii\base\Component;

abstract class Target extends Component
{
    abstract public function export($topic, $part, $message);
}
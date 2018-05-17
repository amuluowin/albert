<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午2:42
 */

namespace yii\swoole\moniter\Collecter;

use Yii;

interface CollecterInterface
{
    public function collect(): ?string;

    public function open();

    public function close();
}
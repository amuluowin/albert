<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19
 * Time: 16:17
 */
declare(strict_types=1);

namespace yii\swoole\base\clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19
 * Time: 16:18
 */
declare(strict_types=1);

namespace yii\swoole\base\clock;

use DateTimeImmutable;

final class FrozenClock implements Clock
{
    /**
     * @var DateTimeImmutable
     */
    private $now;

    public function __construct(DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    public function setTo(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
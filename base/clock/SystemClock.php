<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19
 * Time: 16:19
 */
declare(strict_types=1);

namespace yii\swoole\base\clock;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    /**
     * @var DateTimeZone
     */
    private $timezone;

    public function __construct(DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?: new DateTimeZone(date_default_timezone_get());
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
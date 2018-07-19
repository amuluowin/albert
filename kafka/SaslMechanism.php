<?php
declare(strict_types=1);

namespace yii\swoole\kafka;

interface SaslMechanism
{
    /**
     *
     * sasl authenticate
     *
     * @access public
     */
    public function authenticate(CommonSocket $socket): void;
}

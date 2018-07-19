<?php
declare(strict_types=1);

namespace yii\swoole\kafka\Sasl;

use yii\swoole\kafka\CommonSocket;
use yii\swoole\kafka\Exception;
use yii\swoole\kafka\Protocol;
use yii\swoole\kafka\Protocol\Protocol as ProtocolTool;
use yii\swoole\kafka\SaslMechanism;
use function substr;

abstract class Mechanism implements SaslMechanism
{
    public function authenticate(CommonSocket $socket): void
    {
        $this->handShake($socket, $this->getName());
        $this->performAuthentication($socket);
    }

    /**
     *
     * sasl authenticate hand shake
     *
     * @access protected
     */
    protected function handShake(CommonSocket $socket, string $mechanism): void
    {
        $requestData = Protocol::encode(Protocol::SASL_HAND_SHAKE_REQUEST, [$mechanism]);
        $socket->writeBlocking($requestData);
        $dataLen = ProtocolTool::unpack(\yii\swoole\kafka\Protocol\Protocol::BIT_B32, $socket->readBlocking(4));

        $data          = $socket->readBlocking($dataLen);
        $correlationId = ProtocolTool::unpack(\yii\swoole\kafka\Protocol\Protocol::BIT_B32, substr($data, 0, 4));
        $result        = Protocol::decode(Protocol::SASL_HAND_SHAKE_REQUEST, substr($data, 4));

        if ($result['errorCode'] !== Protocol::NO_ERROR) {
            throw new Exception(Protocol::getError($result['errorCode']));
        }
    }

    abstract protected function performAuthentication(CommonSocket $socket): void;
    abstract public function getName(): string;
}

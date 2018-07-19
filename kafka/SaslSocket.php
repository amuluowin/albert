<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-29
 * Time: 下午8:53
 */

namespace yii\swoole\kafka;

use yii\swoole\kafka\Protocol;
use yii\swoole\kafka\Sasl\Scram;
use yii\swoole\kafka\SocketSync;
use yii\base\BaseObject;

class SaslSocket extends BaseObject
{
    public function init()
    {
        parent::init();
        Protocol::init('1.0.0');
        //$provider = new \yii\swoole\kafka\Sasl\Plain('nmred', '123456');
        //$provider = new \yii\swoole\kafka\Sasl\Gssapi('/etc/security/keytabs/kafkaclient.keytab', 'kafka/node1@NMREDKAFKA.COM');
        $provider = new Scram('alice', 'alice-secret', Scram::SCRAM_SHA_256);
        $socket = new SocketSync('127.0.0.1', '9092', null, $provider);
        $socket->connect();
        $data = [
            'required_ack' => 1,
            'timeout' => '1000',
            'data' => [
                [
                    'topic_name' => 'test',
                    'partitions' => [
                        [
                            'partition_id' => 0,
                            'messages' => [
                                ['key' => 'testkey', 'value' => 'test...'],
                                'test...',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $requestData = Protocol::encode(Protocol::PRODUCE_REQUEST, $data);
        $socket->write($requestData);
        $dataLen = \yii\swoole\kafka\Protocol\Protocol::unpack(\yii\swoole\kafka\Protocol\Protocol::BIT_B32, $socket->readBlocking(4));
        $data = $socket->readBlocking($dataLen);
        $correlationId = \yii\swoole\kafka\Protocol\Protocol::unpack(\yii\swoole\kafka\Protocol\Protocol::BIT_B32, substr($data, 0, 4));
        $result = Protocol::decode(Protocol::PRODUCE_REQUEST, substr($data, 4));
        var_dump($result);
    }
}
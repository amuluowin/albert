<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-23
 * Time: 下午4:47
 */

namespace yii\swoole\udp;


trait UdpTrait
{
    public function onPacket($server, $data, array $client_info)
    {
        $server->sendto($client_info['address'], $client_info['port'], $data);
    }
}
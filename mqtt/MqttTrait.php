<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-24
 * Time: 下午4:43
 */

namespace yii\swoole\mqtt;


trait MqttTrait
{
    function decodeValue($data)
    {
        return 256 * ord($data[0]) + ord($data[1]);
    }

    function decodeString($data)
    {
        $length = $this->decodeValue($data);
        return substr($data, 2, $length);
    }

    function mqtt_get_header($data)
    {
        $byte = ord($data[0]);
        $header['type'] = ($byte & 0xF0) >> 4;
        $header['dup'] = ($byte & 0x08) >> 3;
        $header['qos'] = ($byte & 0x06) >> 1;
        $header['retain'] = $byte & 0x01;
        return $header;
    }

    function event_connect($header, $data)
    {
        $connect_info['protocol_name'] = $this->decodeString($data);
        $offset = strlen($connect_info['protocol_name']) + 2;
        $connect_info['version'] = ord(substr($data, $offset, 1));
        $offset += 1;
        $byte = ord($data[$offset]);
        $connect_info['willRetain'] = ($byte & 0x20 == 0x20);
        $connect_info['willQos'] = ($byte & 0x18 >> 3);
        $connect_info['willFlag'] = ($byte & 0x04 == 0x04);
        $connect_info['cleanStart'] = ($byte & 0x02 == 0x02);
        $offset += 1;
        $connect_info['keepalive'] = $this->decodeValue(substr($data, $offset, 2));
        $offset += 2;
        $connect_info['clientId'] = $this->decodeString(substr($data, $offset));
        return $connect_info;
    }

    public function onReceive($serv, $fd, $from_id, $data)
    {
        $header = $this->mqtt_get_header($data);
        if ($header['type'] == 1) {
            $resp = chr(32) . chr(2) . chr(0) . chr(0);//转换为二进制返回应该使用chr
            $this->event_connect($header, substr($data, 2));
            $serv->send($fd, $resp);
        } elseif ($header['type'] == 3) {
            $offset = 2;
            $topic = $this->decodeString(substr($data, $offset));
            $offset += strlen($topic) + 2;
            $msg = substr($data, $offset);
            echo "client msg: $topic\n---------------------------------\n$msg\n";
        }
    }
}
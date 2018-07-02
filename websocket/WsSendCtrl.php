<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-12
 * Time: 下午5:08
 */

namespace yii\swoole\websocket;

use Yii;
use yii\base\Component;

class WsSendCtrl extends Component implements WsSendInterface
{

    public function send($server, $data, $to = null)
    {
        if ($to) {
            if (is_array($to)) {
                foreach ($to as $client) {
                    $server->push($client, $data);
                }
            } else {
                $server->push($to, $data);
            }
        }
    }

    public function sendDataByUser($server, $data)
    {
        foreach ($data as $fd => $content) {
            $server->push($fd, $content);
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-12
 * Time: 下午5:08
 */

namespace yii\swoole\web;

use Yii;
use yii\base\Component;

class WsSendCtrl extends Component implements WsSendInterface
{

    public function sendTo($server, $data, $callback = null, $fd = null, $to = null)
    {
        if ($to) {
            if (is_array($to)) {
                foreach ($to as $client) {
                    $server->push(Yii::$app->usercache->get('wsclient:' . $client)['fd'], $data);
                }
            } else {
                $server->push(Yii::$app->usercache->get('wsclient:' . $to)['fd'], $data);
            }
        } elseif ($callback && is_callable($callback)) {
            foreach (call_user_func($callback) as $client) {
                $server->push(Yii::$app->usercache->get('wsclient:' . $client)['fd'], $data);
            }
        } elseif ($fd) {
            $server->push($fd, Yii::$app->getResponse()->content);
        }

    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-12
 * Time: 下午3:58
 */

namespace yii\swoole\web;

use Yii;
use yii\base\BaseObject;

class WebsocketAuth extends BaseObject implements WsAuthInterface
{
    public function handShake($server, $request)
    {
        if (!isset($request->get['auth_token'])) {
            return false;
        }
        Yii::$app->cache->set('websocketheaders', $request->header);
        Yii::$app->usercache->set('wsclient:' . $request->get['auth_token'], ['fd' => $request->fd]);
        return true;
    }


}
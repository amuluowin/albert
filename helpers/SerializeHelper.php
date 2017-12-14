<?php

namespace yii\swoole\helpers;

class SerializeHelper
{

    public static function serialize($data)
    {
        if (extension_loaded('swoole') && !empty($data)) {
            return \Swoole\Serialize::pack($data);
        } else {
            return serialize($data);
        }
    }

    public static function unserialize($data)
    {
        if (extension_loaded('swoole') && !empty($data)) {
            return \Swoole\Serialize::unpack($data);
        } else {
            return unserialize($data);
        }
    }

}

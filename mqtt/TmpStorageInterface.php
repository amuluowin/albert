<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 2017/8/5
 * Time: 下午5:28
 */

namespace yii\swoole\mqtt;


interface TmpStorageInterface
{

    public function set($message_type, $key, $sub_key, $data, $expire = 3600);

    public function get($message_type, $key, $sub_key);

    public function delete($message_type, $key, $sub_key);
}
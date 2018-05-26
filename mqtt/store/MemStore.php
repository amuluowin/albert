<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-25
 * Time: 下午4:42
 */

namespace yii\swoole\mqtt\store;


use yii\base\Component;

class MemStore extends Component implements TmpStorageInterface
{
    /**
     * @var array
     */
    private $data = [];

    public function set($message_type, $key, $sub_key, $data, $expire = 3600)
    {
        $this->data[$message_type][$key][$sub_key] = $data;
    }

    public function get($message_type, $key, $sub_key)
    {
        return $this->data[$message_type][$key][$sub_key];
    }

    public function delete($message_type, $key, $sub_key)
    {
        if (!isset($this->data[$message_type][$key][$sub_key])) {
            echo "storage not found:$message_type $key $sub_key";
        }
        unset($this->data[$message_type][$key][$sub_key]);
    }
}
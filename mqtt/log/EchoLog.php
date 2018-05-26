<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-25
 * Time: 下午4:44
 */

namespace yii\swoole\mqtt\log;


use yii\base\Component;

class EchoLog extends Component implements MqttLogInterface
{
    /**
     * @var array
     */
    public $log_level = [];

    public function log($type, $content, $params = [])
    {
        if (in_array($type, $this->log_level)) {
            echo "$type : $content" . PHP_EOL;
        }
    }
}
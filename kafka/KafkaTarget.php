<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-30
 * Time: 下午4:26
 */

namespace yii\swoole\kafka;

use Yii;
use yii\log\Target;
use yii\swoole\Application;
use yii\swoole\kafka\Kafka;

class KafkaTarget extends Target
{
    /**
     * @var string
     */
    public $topic = APP_NAME;

    /**
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        $result = [];
        foreach ($this->logVars as $key) {
            if (isset($GLOBALS[$key])) {
                $result[] = "\${$key} = " . print_r($GLOBALS[$key], true);
            }
        }
        return implode("\n\n", $result);
    }

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export()
    {
        /**
         * @var Kafka $kafka
         */
        if (($kafka = Yii::$app->get('kafka', false)) !== null
            && isset($kafka->producer)) {
            $kafka->send([
                [
                    'topic' => $this->topic,
                    'value' => implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n",
                    'key' => 'SystemLog',
                ],
            ]);
        }
    }
}
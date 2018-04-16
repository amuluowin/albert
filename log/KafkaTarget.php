<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-3-30
 * Time: 下午4:26
 */

namespace yii\swoole\log;

use Yii;
use yii\log\Target;
use yii\swoole\Application;

class KafkaTarget extends Target
{
    /**
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        if (!Application::$workerApp) {
            return parent::getContextMessage();
        }
        // 原来的上下文格式化函数, VarDumper太耗时了, 改成直接print_r, 虽然样式丢失不了, 但是效率提升不少
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
        if (isset(Yii::$app->kafka) && isset(Yii::$app->kafka->producer)) {
            Yii::$app->kafka->producer->send([
                [
                    'topic' => 'test',
                    'value' => implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n",
                    'key' => Yii::$app->request->getTraceId(),
                ],
            ]);
        }
    }
}
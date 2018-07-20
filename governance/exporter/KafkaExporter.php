<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午8:36
 */

namespace yii\swoole\governance\exporter;

use Yii;
use yii\base\Component;
use yii\helpers\VarDumper;
use yii\swoole\kafka\Kafka;

class KafkaExporter extends Component implements ExportInterface
{
    /**
     * @var string
     */
    public $topic = 'trace';

    public function export($data, string $key = null)
    {
        /**
         * @var Kafka $kafka
         */
        if (($kafka = Yii::$app->get('kafka', false)) !== null) {
            $kafka->send([
                [
                    'topic' => $this->topic,
                    'value' => VarDumper::export($data),
                    'key' => APP_NAME,
                ],
            ]);
        }
    }
}
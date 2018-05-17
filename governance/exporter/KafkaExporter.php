<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午8:36
 */

namespace yii\swoole\governance\exporter;

use Yii;
use yii\helpers\VarDumper;

class KafkaExporter implements ExportInterface
{
    /**
     * @var string
     */
    public $topic = 'trace';

    public function export($data, string $key = null)
    {
        if (($kafka = Yii::$app->get('kafka', false)) !== null
            && isset($kafka->producer)) {
            $kafka->producer->send([
                [
                    'topic' => 'trace',
                    'value' => VarDumper::export($data),
                    'key' => $key,
                ],
            ]);
        }
    }
}
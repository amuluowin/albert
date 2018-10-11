<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/11
 * Time: 20:18
 */

namespace yii\swoole\governance\exporter;

use Yii;
use yii\base\Component;

class SeaslogExporter extends Component implements ExportInterface
{
    public function export($data, string $key = null)
    {
        Yii::info($data, 'trace', 'trace');
    }
}
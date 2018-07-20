<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/20
 * Time: 14:16
 */

namespace yii\swoole\governance\exporter;


use yii\base\Component;
use yii\helpers\VarDumper;

class FileExporter extends Component implements ExportInterface
{
    /**
     * @var string
     */
    public $topic = 'trace';

    public $logFile;

    public function init()
    {
        if ($this->logFile === null) {
            $this->logFile = Yii::$app->getRuntimePath() . '/logs';
        } else {
            $this->logFile = Yii::getAlias($this->logFile);
        }
    }

    public function export($data, string $key = null)
    {
        go(function () use ($data) {
            FileIO::write($this->logFile . '/' . $this->topic . '.log', 'System:' . APP_NAME . ' ' . VarDumper::export($data) . PHP_EOL, FILE_APPEND);
        });
    }
}
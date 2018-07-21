<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/20
 * Time: 14:16
 */

namespace yii\swoole\governance\exporter;

use Yii;
use yii\base\Component;
use yii\helpers\VarDumper;
use yii\swoole\files\FileTarget;

class FileExporter extends Component implements ExportInterface
{
    /**
     * @var string
     */
    public $topic = 'trace';

    public $logFile;

    /**
     * @var FileTarget $target
     */
    public $target;

    public function init()
    {
        if (!$this->target instanceof FileTarget) {
            $this->target = Yii::createObject($this->target);
        }
    }

    public function export($data, string $key = null)
    {
        go(function () use ($data) {
            $this->target->export(APP_NAME . ':' . VarDumper::export($data));
        });
    }
}
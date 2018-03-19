<?php

namespace yii\swoole\clog\collecters;

use Yii;
use yii\helpers\VarDumper;
use yii\swoole\clog\BaseCollecter;
use yii\swoole\files\FileIO;

class FileCollecter extends BaseCollecter
{
    public $filename;

    public function init()
    {
        if (!$this->filename) {
            $this->filename = Yii::getAlias('@runtime/logs/traceLog.log');
        }
    }

    public function write($data)
    {
        $content = date('Y-m-d H:i:s', time()) . PHP_EOL . VarDumper::export($data) . PHP_EOL;
        FileIO::write($this->filename, $content, FILE_APPEND);
    }
}
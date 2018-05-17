<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午4:23
 */

namespace yii\swoole\moniter\Exporter;

use Yii;
use yii\base\InvalidArgumentException;
use yii\swoole\base\Channel;

class FileWriter extends Channel implements ExporterInterface
{
    public $path;

    private $fp;

    public function init()
    {
        parent::init();
        $this->path = Yii::getAlias($this->path);
    }

    public function export(string $data)
    {
        \Co::fwrite($this->fp, $data);
    }

    public function open()
    {
        try {
            if (($this->fp = @fopen($this->path, "w+")) === false) {
                throw new InvalidArgumentException("Unable to open file: $this->path");
            }
            @flock($this->fp, LOCK_EX);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function close()
    {
        @flock($this->fp, LOCK_UN);
        @fclose($this->fp);
    }
}
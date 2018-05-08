<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午2:38
 */

namespace yii\swoole\moniter\Reader;

use Yii;
use yii\base\InvalidArgumentException;
use yii\swoole\base\Channel;

class NginxReader extends Channel implements ReaderInterface
{
    public $path;

    private $fp;

    public function read(): ?string
    {
        if ($this->path && file_exists($this->path)) {
            try {
                $content = \Co::fgets($this->fp);
                return $content;
            } catch (\Exception $e) {
                sprintf((string)$e);
                return null;
            }
        }
        return null;
    }

    public function open()
    {
        try {
            if (($this->fp = @fopen($this->path, 'r+')) === false) {
                throw new InvalidArgumentException("Unable to open file: $this->path");
            }
            @fseek($this->fp, 0, SEEK_END);
            @flock($this->fp, LOCK_SH);
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
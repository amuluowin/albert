<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-1-9
 * Time: 下午3:43
 */

namespace yii\swoole\files;

use yii\base\InvalidConfigException;
use yii\swoole\Application;

class FileIO
{
    public static function write($filename, $data)
    {
        if (Application::$workerApp) {
            try {
                if (($fp = @fopen($filename, "a+")) === false) {
                    throw new InvalidConfigException("Unable to open file: $filename");
                }
                \Swoole\Coroutine::fwrite($fp, $data);
                @fclose($fp);
                return true;
            } catch (\Exception $e) {
                return false;
            }

        } else {
            return file_put_contents($filename, $data);
        }

    }

    public static function read($filename)
    {
        if (Application::$workerApp) {
            try {
                if (($fp = @fopen($filename, 'r+')) === false) {
                    throw new InvalidConfigException("Unable to open file: $filename");
                }
                $content = \Swoole\Coroutine::fread($fp);
                @fclose($fp);
                return $content;
            } catch (\Exception $e) {
                return false;
            }

        } else {
            return file_get_contents($filename);
        }
    }
}
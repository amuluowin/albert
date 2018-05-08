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

    public static function write(string $filename, string $data, int $flags = 0): bool
    {
        if (Application::$workerApp) {
            try {
                if (($fp = @fopen($filename, $flags ? "a+" : "w+")) === false) {
                    throw new InvalidConfigException("Unable to open file: $filename");
                }
                @flock($fp, LOCK_EX);
                \Swoole\Coroutine::fwrite($fp, $data);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                return true;
            } catch (\Exception $e) {
                return false;
            }

        } else {
            return file_put_contents($filename, $data, $flags);
        }

    }

    public static function read(string $filename)
    {
        if (Application::$workerApp) {
            try {
                if (($fp = @fopen($filename, 'r+')) === false) {
                    throw new InvalidConfigException("Unable to open file: $filename");
                }
                @flock($fp, LOCK_SH);
                $content = \Swoole\Coroutine::fread($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                return $content;
            } catch (\Exception $e) {
                return false;
            }

        } else {
            return file_get_contents($filename);
        }
    }

    public static function gets(string $filename, int $index = 0, int $seek = 0)
    {
        try {
            if (($fp = @fopen($filename, 'r+')) === false) {
                throw new InvalidConfigException("Unable to open file: $filename");
            }
            @flock($fp, LOCK_SH);
            @fseek($fp, $index, $seek);
            $content = \Swoole\Coroutine::fgets($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return $content;
        } catch (\Exception $e) {
            return false;
        }
    }
}
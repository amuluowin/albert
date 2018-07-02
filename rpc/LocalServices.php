<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-05-29
 * Time: 1:10
 */

namespace yii\swoole\rpc;

use Yii;

class LocalServices
{
    public static function getServices()
    {
        $dir = Yii::getAlias('@services', false);
        $services = [];
        try {
            $files = array();
            $queue = array($dir);
            while ($data = each($queue)) {
                $path = $data['value'];
                if (is_dir($path) && $handle = opendir($path)) {
                    while ($file = readdir($handle)) {
                        if ($file == '.' || $file == '..') {
                            continue;
                        }
                        $real_path = $path . '/' . $file;
                        if (is_dir($real_path)) {
                            $queue[] = $real_path;
                        } elseif (strpos($real_path, 'Logic') !== false) {
                            $file = str_replace(['services/', '.php'], ['', ''], substr($real_path, strpos($real_path, 'services')));
                            $file = explode('/', $file);
                            $files[array_shift($file)][] = array_shift($file);
                        }
                    }
                }
                closedir($handle);
            }
            return $files;
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), __METHOD__);
        }
    }

    public static function getApis()
    {
        $modules = [];
        foreach (Yii::$app->getModules() as $module) {
            $modules[] = $module->id;
        }
        return $modules;
    }

    public static function getCurCall($service, $route, $method)
    {
        return ["services\\" . $service . "\\" . $route, $method];
    }
}
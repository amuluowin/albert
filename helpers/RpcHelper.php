<?php

namespace yii\swoole\helpers;

use Yii;
use yii\swoole\Module;
use yii\swoole\rpc\RpcRoute;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/8 0008
 * Time: 10:11
 */
class RpcHelper extends \yii\base\Component
{

    private static $_modules = [];

    /*
    * 获取模块列表
    */

    public function getModules($mod = null)
    {
        if (!self::$_modules) {
            $this->getModuleList($mod);
        }

        return self::$_modules;

    }

    private function getModuleList($mod = null)
    {
        $mod = $mod ? $mod : Yii::$app->getModules(true);
        foreach ($mod as $k => $v) {
            $m = explode('\\', str_replace('\Module', '', $v::className()));
            $m = $m[count($m) - 1];
            if (($v instanceof Module) && $v->isService) {
                self::$_modules[$m] = $v->id;
            }
            if ($v->modules) {
                $this->getModuleList($v->modules);
            }
        }
    }

    /*
     * 获取业务文件
     */
    public function getLogics($path = null)
    {
        return $this->getLogicsList($path);
    }

    private function findModule($value)
    {
        foreach ($this->getModules() as $namespace => $id) {
            if (strpos($namespace, $value) !== false) {
                return $id;
            }
        }
    }

    private function getLogicsList($path = null, $logics = [])
    {
        $path = $path ?: Yii::getAlias('@addons');
        foreach (glob($path . '/*') as $file) {
            if (is_dir($file)) {
                $logics = $this->getLogicsList($file, $logics);
            } elseif (strpos($file, 'Controller.php') !== false) {
                $data = explode('/', str_replace([Yii::getAlias('@addons/'), 'controllers/', 'Controller.php'], ['', '', ''], $file));
                $basename = '';
                foreach ($data as $index => $value) {
                    if ($index === count($data) - 1 && $basename != '') {
                        $basename .= '/' . strtolower($value);
                    } else {
                        $module = $this->findModule($value);
                        $basename .= $module ? '/' . $module : '';
                    }
                }
                if ($basename && !in_array($basename, $logics)) {
                    $logics[] = $basename;
                }
            } elseif (strpos($file, '.php') !== false &&
                (strpos($file, 'modellogic') !== false || strpos($file, 'customba'))
            ) {
                $basename = 'addons' . substr(str_replace('/', '\\', str_replace(Yii::getAlias('@addons'), '', $file)), 0, -4);
                if (preg_match("/\\\(.*?)\\\modellogic/", $basename, $m) &&
                    isset($m[1]) && $this->findModule($m[1])) {
                    if ($basename && !in_array($basename, $logics)) {
                        $logics[] = $basename;
                    }
                }
            }
        }
        return $logics;
    }

    /*
     * 获取所有controller
     */

    public function getRoutes($module = null)
    {
        return RpcRoute::getAppRoutes($module);
    }

    private static $_rpcClass;

    /*
     * 获取RPC列表
     */
    public function getRpcClass()
    {
        if (!self::$_rpcClass) {
            self::$_rpcClass = ArrayHelper::merge($this->getLogics(), ArrayHelper::getValue(Yii::$app->rpc, 'selfList', []));
        }
        return self::$_rpcClass;
    }

    private static $_rpcLen = 0;

    /*
     * 获取RPC列表长度
     */
    public function getRpcLen()
    {
        if (!self::$_rpcLen) {
            self::$_rpcLen = strlen(implode(';', $this->getRpcClass()));
        }
        return self::$_rpcLen;
    }
}
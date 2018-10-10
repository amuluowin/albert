<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/8
 * Time: 8:59
 */

namespace yii\swoole\base;

use Yii;
use yii\swoole\helpers\CoroHelper;

class Context
{
    private static $context = [];

    public static function getAll()
    {
        return self::$context[CoroHelper::getId()];
    }

    public static function setAll($config = [])
    {
        foreach ($config as $name => $value) {
            self::set($name, $value);
        }
    }

    public static function get(string $name)
    {
        $id = CoroHelper::getId();
        if (isset(self::$context[$id][$name])) {
            if (is_array(self::$context[$id][$name]) && isset(self::$context[$id][$name]['class'])) {
                self::$context[$id][$name] = Yii::createObject(self::$context[$id][$name]);
            }
        } elseif (isset(self::$context[0][$name])) {
            if (is_array(self::$context[0][$name]) && isset(self::$context[0][$name]['class'])) {
                self::$context[0][$name] = Yii::createObject(self::$context[0][$name]);
            }
            self::$context[$id][$name] = is_object(self::$context[0][$name]) ? clone self::$context[0][$name] : self::$context[0][$name];

        } else {
            $obj = Yii::$app->get($name, false);
            self::$context[$id][$name] = is_object($obj) ? clone $obj : $obj;
        }

        return self::$context[$id][$name];
    }

    public static function set(string $name, $value)
    {
        self::$context[CoroHelper::getId()][$name] = $value;
    }

    public static function has($name): bool
    {
        return isset(self::$context[CoroHelper::getId()][$name]) || isset(self::$context[0][$name]);
    }

    public static function release()
    {
        unset(self::$context[CoroHelper::getId()]);
    }
}
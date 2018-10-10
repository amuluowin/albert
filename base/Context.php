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

    private static $components = [];

    public static function setComponents(array $components)
    {
        self::$components = $components;
    }

    public static function getComponents(): array
    {
        return self::$components;
    }

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
        } else {
            self::$context[$id][$name] = key_exists($name, self::$components) ? Yii::createObject(self::$components[$name]) : null;
        }
        return self::$context[$id][$name];
    }

    public static function set(string $name, $value)
    {
        self::$context[CoroHelper::getId()][$name] = $value;
    }

    public static function has($name): bool
    {
        return isset(self::$context[CoroHelper::getId()][$name]);
    }

    public static function release()
    {
        unset(self::$context[CoroHelper::getId()]);
    }
}
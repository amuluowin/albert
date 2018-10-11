<?php
/**
 * Yii bootstrap file.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

require(__DIR__ . '/../../yiisoft/yii2/BaseYii.php');

/**
 * Yii is a helper class serving common framework functionalities.
 *
 * It extends from [[\yii\BaseYii]] which provides the actual implementation.
 * By writing your own Yii class, you can customize some functionalities of [[\yii\BaseYii]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Yii extends \yii\BaseYii
{
    public static $server;

    public static $apis = [];

    public static $confKeys = [];

    public static $rpcList = [];

    private static $_logger;

    public static function getLogger()
    {
        if (self::$_logger !== null) {
            return self::$_logger;
        }
        if (($logger = \yii\swoole\base\Context::get('logger')) !== null) {
            return $logger;
        }
        $logger = isset(static::$app->getComponents()['log']['logger']) ? static::createObject(static::$app->getComponents()['log']['logger']) : static::createObject('yii\log\Logger');
        if ($logger instanceof \yii\swoole\seaslog\Logger) {
            self::$_logger = $logger;
        } else {
            \yii\swoole\base\Context::set('logger', $logger);
        }
        return $logger;
    }

    public static function configure($object, $properties)
    {
        foreach ($properties as $name => $value) {
            if (is_array($value) && isset($value['class'])) {
                $object->$name = self::createObject($value);
            } else {
                $object->$name = $value;
            }
        }

        return $object;
    }

    public static function autoload($className)
    {
        if (strpos($className, '\\') !== false) {
            $classFile = static::getAlias('@' . str_replace('\\', '/', $className) . '.php', false);
            if ($classFile === false || !is_file($classFile)) {
                return;
            }
        } else {
            return;
        }

        if (!in_array($className, static::$classMap)) {
            static::$classMap[] = $className;
        }

        include($classFile);

        if (YII_DEBUG && !class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false)) {
            throw new \yii\base\UnknownClassException("Unable to find '$className' in file: $classFile. Namespace missing?");
        }
    }

    public static function debug($message, $category = 'application', $module = APP_NAME)
    {
        if (YII_DEBUG && static::getLogger()->isFlush) {
            static::getLogger()->log($message, \yii\log\Logger::LEVEL_TRACE, $category, $module);
        }
    }

    public static function error($message, $category = 'application', $module = APP_NAME)
    {
        if (static::getLogger()->isFlush) {
            static::getLogger()->log($message, \yii\log\Logger::LEVEL_ERROR, $category, $module);
        }
    }

    public static function warning($message, $category = 'application', $module = APP_NAME)
    {
        if (static::getLogger()->isFlush) {
            static::getLogger()->log($message, \yii\log\Logger::LEVEL_WARNING, $category, $module);
        }
    }

    public static function info($message, $category = 'application', $module = APP_NAME)
    {
        if (static::getLogger()->isFlush) {
            static::getLogger()->log($message, \yii\log\Logger::LEVEL_INFO, $category, $module);
        }
    }

    public static function beginProfile($token, $category = 'application', $module = APP_NAME)
    {
//        if (static::getLogger()->isFlush) {
//            static::getLogger()->log($token, \yii\log\Logger::LEVEL_PROFILE_BEGIN, $category,$module);
//        }
    }

    public static function endProfile($token, $category = 'application', $module = APP_NAME)
    {
//        if (static::getLogger()->isFlush) {
//            static::getLogger()->log($token, \yii\log\Logger::LEVEL_PROFILE_END, $category,$module);
//        }
    }
}

spl_autoload_register(['Yii', 'autoload'], true, true);
Yii::$container = new \yii\swoole\Container();

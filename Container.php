<?php

namespace yii\swoole;

use ReflectionClass;
use Yii;
use yii\base\BaseObject;

/**
 * 继承原有的容器, 实现一些额外的逻辑
 *
 * @package yii\swoole
 */
class Container extends \yii\di\Container
{

    /**
     * @var array 类的别名
     */
    public static $classAlias = [
        'yii\web\Session' => 'yii\swoole\web\Session',
        'yii\db\Query' => 'yii\swoole\db\Query'
    ];

    public function init()
    {
        self::$classAlias['yii\log\Logger'] = getenv('LOGGER') ? getenv('LOGGER') : 'yii\swoole\log\Logger';
    }

    /**
     * @var array 持久化的类实例
     */
    public static $persistInstances = [];

    /**
     * 在最终构造类时, 尝试检查类的别名
     *
     * @inheritdoc
     */
    protected function build($class, $params, $config)
    {
        // 检查类的别名
        if (isset(self::$classAlias[$class])) {
            $class = self::$classAlias[$class];
        }

        // 构造方法参数为空才走这个流程
        if ($class && in_array($class, Yii::$classMap)) {
            /* @var $reflection ReflectionClass */
            list ($reflection, $dependencies) = $this->getDependencies($class);
            if (!isset(self::$persistInstances[$class])) {
                self::$persistInstances[$class] = $reflection->newInstanceWithoutConstructor();
            }
            $object = clone self::$persistInstances[$class];
            // 如果有params参数的话, 则交给构造方法去执行
            // 这里的逻辑貌似太简单了..
            if ($params) {
                $reflection->getConstructor()->invokeArgs($object, $params);
            }
            // 执行一些配置信息
            Yii::configure($object, $config);
            if ($object instanceof BaseObject) {
                $object->init();
            }
            return $object;
        }

        return parent::build($class, $params, $config);
    }

}

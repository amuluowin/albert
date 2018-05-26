<?php

namespace yii\swoole\base;

use yii\swoole\helpers\ArrayHelper;

trait SingletonTrait
{
    /**
     * @var object
     */
    protected static $instance;

    /**
     * @return object
     */
    public static function getInstance($key = null, $config = null)
    {
        if (self::$instance === null) {
            static::$instance = new static($key, $config);
        }

        return static::$instance;
    }

    public function __construct($key = null, $config = null)
    {
        if ($key && $config) {
            $this->config = ArrayHelper::merge(ArrayHelper::getValue($config, $key), ArrayHelper::getValue($config, 'common'));
            if (isset($this->config['name'])) {
                $this->name = $this->config['name'];
            }
            if (isset($this->config['pidFile'])) {
                $this->pidFile = $this->config['pidFile'];
            }
        }
    }
}

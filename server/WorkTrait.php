<?php

namespace yii\swoole\server;

use Yii;
use yii\swoole\Application;
use yii\swoole\Container;
use yii\swoole\helpers\ArrayHelper;

trait WorkTrait
{
    public function workerStart($server = null, $worker_id)
    {
        //开启Hook
//        \Swoole\Runtime::enableCoroutine();
        // 关闭Yii2自己实现的异常错误
        defined('YII_ENABLE_ERROR_HANDLER') || define('YII_ENABLE_ERROR_HANDLER', false);
        // 加载文件和一些初始化配置

        foreach (ArrayHelper::remove($this->config, 'bootstrapFile', []) as $file) {
            require $file;
        }

        $config = [];

        foreach ($this->config['configFile'] as $file) {
            $config = ArrayHelper::merge($config, include $file);
        }

        if (isset($this->config['bootstrapRefresh'])) {
            $config['bootstrapRefresh'] = $this->config['bootstrapRefresh'];
        }

        $config['aliases']['@webroot'] = $this->root;
        $config['aliases']['@web'] = '/';
        new Application($config);
        Yii::$server = $server;
        Yii::$app->language = $config['language'];
        Application::$workerApp = true;
        // init all yii components
        foreach ($config['components'] as $id => $_config) {
            Yii::$app->get($id);
        }

        Yii::$app->setRootPath($this->root);

        Yii::$app->prepare($this);
    }
}
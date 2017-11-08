<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\tablecache;

use Yii;
use yii\base\InvalidConfigException;

class Session extends \yii\swoole\web\Session
{
    public $tablecache = 'cache';

    public $keyPrefix;

    public function init()
    {
        if (is_string($this->tablecache)) {
            $this->tablecache = Yii::$app->get($this->tablecache);
        } elseif (is_array($this->tablecache)) {
            if (!isset($this->tablecache['class'])) {
                $this->tablecache['class'] = Cache::className();
            }
            $this->tablecache = Yii::createObject($this->tablecache);
        }
        if (!$this->tablecache instanceof Cache) {
            throw new InvalidConfigException("Session::redis must be either a Redis connection instance or the application component ID of a Redis connection.");
        }
        if ($this->keyPrefix === null) {
            $this->keyPrefix = substr(md5(Yii::$app->id), 0, 5);
        }
        register_shutdown_function([$this, 'close']);
        if ($this->getIsActive()) {
            Yii::warning('Session is already started', __METHOD__);
            $this->updateFlashCounters();
        }
    }

    public function getUseCustomStorage()
    {
        return true;
    }

    public function readSession($id)
    {
        $data = $this->tablecache->get($this->calculateKey($id));

        return $data === false || $data === null ? '' : $data;
    }

    public function writeSession($id, $data)
    {
        return (bool)$this->tablecache->set($this->calculateKey($id), $data, $this->getTimeout());
    }

    public function destroySession($id)
    {
        return $this->tablecache->delete($this->calculateKey($id));
    }

    protected function calculateKey($id)
    {
        return $this->keyPrefix . md5(json_encode([__CLASS__, $id]));
    }
}

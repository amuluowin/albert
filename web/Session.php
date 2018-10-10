<?php

namespace yii\swoole\web;

use Yii;
use yii\swoole\helpers\SerializeHelper;
use yii\web\Cookie;

/**
 * Class Session
 *
 * @property string sessionKey
 * @property swoole_http_response swooleResponse
 */
class Session extends \yii\web\Session
{
    /**
     * @var string
     */
    protected $_sessionKey = 'JSESSIONID';

    protected $_sessionName = 'PHPSESSID';

    protected $_session;

    /**
     * @return string
     */
    public function getSessionKey()
    {
        return $this->_sessionKey;
    }

    /**
     * @param string $sessionKey
     */
    public function setSessionKey($sessionKey)
    {
        $this->_sessionKey = $sessionKey;
    }

    /**
     * @return string the current session name
     */
    public function getName()
    {
        return $this->_sessionName;
    }

    /**
     * It defaults to "PHPSESSID".
     */
    public function setName($value)
    {
        $this->_sessionName = $value;
    }

    /**
     * @var string
     */
    protected $_id = [];

    /**
     * 从cookie中取session id
     *
     * @return string
     */
    public function getId()
    {
        if (isset($this->_id)) {
            return $this->_id;
        }
        $cookie = Yii::$app->getRequest()->getCookies()->get($this->sessionKey);
        if ($cookie) {
            return $cookie->value;
        }
        return null;
    }

    /**
     * @param string $value
     */
    public function setId($value)
    {
        $cookie = new Cookie([
            'name' => $this->sessionKey,
            'value' => $value
        ]);
        $this->_id = $value;
        Yii::$app->response->getCookies()->add($cookie);
    }

    /**
     * @var bool
     */
    protected $_isActive = [];

    /**
     * 判断当前是否使用了session
     */
    public function getIsActive()
    {
        return isset($this->_isActive) ? $this->_isActive : false;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive($isActive)
    {
        $this->_isActive = $isActive;
    }

    /**
     * 打开会话连接, 从redis中加载会话数据
     *
     * @inheritdoc
     */
    public function open()
    {
        if ($this->getIsActive()) {
            Yii::info('Session started', __METHOD__);
            return;
        }
        $this->setIsActive(true);
        if (!Yii::$app->getRequest()->cookies->has($this->sessionKey)) {
            $this->regenerateID();
        }
        $sid = $this->getId();
        if (!empty($sid)) {
            $data = SerializeHelper::unserialize($this->readSession($sid));
            $this->_session = is_array($data) ? $data : [];
        }
    }

    /**
     * 关闭连接时, 主动记录session到redis
     *
     * @inheritdoc
     */
    public function close()
    {
        // 如果当前会话激活了, 则写session
        if ($this->getIsActive()) {
            // 将session数据存放到redis咯
            $sid = $this->getId();
            $this->writeSession($sid, SerializeHelper::serialize($this->_session));
            // 清空当前会话数据
        }
        $this->setIsActive(false);
        Yii::info('Session closed', __METHOD__);
    }

    /**
     * 自定义生成会话ID
     *
     * @inheritdoc
     */
    public function regenerateID($deleteOldSession = false)
    {
        if ($deleteOldSession) {
            $id = $this->getId();
            $this->destroySession($id);
        }
        $id = 'S' . md5(Yii::$app->security->generateRandomString());
        $this->setId($id);
    }

    /**
     * 判断当前会话是否使用了cookie来存放标识
     * 在swoole中, 暂时只支持cookie标识, 所以只会返回true
     *
     * @inheritdoc
     */
    public function getUseCookies()
    {
        return true;
    }

    public function destroy()
    {
        if ($this->getIsActive()) {
            $sessionId = $this->getId();
            $this->close();
            $this->setId($sessionId);
            $this->open();
            $this->setId($sessionId);
        }
    }

    private $_hasSessionId = [];

    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            $name = $this->getName();
            $request = Yii::$app->getRequest();
            $this->_hasSessionId = $request->get($name) != '';
        }
        return $this->_hasSessionId;
    }

    public function getCount()
    {
        $this->open();
        return count($this->_session);
    }

    public function get($key, $defaultValue = null)
    {
        $this->open();
        return isset($this->_session[$key]) ? $this->_session[$key] : $defaultValue;
    }

    public function set($key, $value)
    {
        $this->open();
        $this->_session[$key] = $value;
    }

    public function remove($key)
    {
        $this->open();
        if (isset($this->_session[$key])) {
            $value = $this->_session[$key];
            unset($this->_session[$key]);

            return $value;
        } else {
            return null;
        }
    }

    public function removeAll()
    {
        $this->open();
        foreach (array_keys($this->_session) as $key) {
            unset($this->_session[$key]);
        }
    }

    public function has($key)
    {
        $this->open();
        return isset($this->_session[$key]);
    }

    protected function updateFlashCounters()
    {
        $counters = $this->get($this->flashParam, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $this->_session[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $this->_session[$this->flashParam] = $counters;
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            unset($this->_session[$this->flashParam]);
        }
    }

    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                $counters[$key] = 1;
                $this->_session[$this->flashParam] = $counters;
            }

            return $value;
        } else {
            return $defaultValue;
        }
    }

    public function getAllFlashes($delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $this->_session)) {
                $flashes[$key] = $this->_session[$key];
                if ($delete) {
                    unset($counters[$key], $this->_session[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }

        $this->_session[$this->flashParam] = $counters;

        return $flashes;
    }

    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->_session[$key] = $value;
        $this->_session[$this->flashParam] = $counters;
    }

    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->_session[$this->flashParam] = $counters;
        if (empty($this->_session[$key])) {
            $this->_session[$key] = [$value];
        } else {
            if (is_array($this->_session[$key])) {
                $this->_session[$key][] = $value;
            } else {
                $this->_session[$key] = [$this->_session[$key], $value];
            }
        }
    }

    public function removeFlash($key)
    {
        $counters = $this->get($this->flashParam, []);
        $value = isset($this->_session[$key], $counters[$key]) ? $this->_session[$key] : null;
        unset($counters[$key], $this->_session[$key]);
        $this->_session[$this->flashParam] = $counters;

        return $value;
    }

    public function removeAllFlashes()
    {
        $counters = $this->get($this->flashParam, []);
        foreach (array_keys($counters) as $key) {
            unset($this->_session[$key]);
        }
        unset($this->_session[$this->flashParam]);
    }

    public function offsetExists($offset)
    {
        $this->open();

        return isset($this->_session[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->open();

        return isset($this->_session[$offset]) ? $this->_session[$offset] : null;
    }

    public function offsetSet($offset, $item)
    {
        $this->open();
        $this->_session[$offset] = $item;
    }

    public function offsetUnset($offset)
    {
        $this->open();
        unset($this->_session[$offset]);
    }

    public function release()
    {
        $this->close();
    }
}

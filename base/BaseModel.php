<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-05-29
 * Time: 19:37
 */

namespace yii\swoole\base;


class BaseModel
{
    /**
     * @var array
     */
    private $_attributes = [];

    public function __construct(array $config = [])
    {
        self::toObject($this, $config);
    }

    public function __get($name)
    {
        if (!isset($this->_attributes[$name])) {
            return null;
        }
        return $this->_attributes[$name];
    }

    public function __set($name, $value)
    {
        $this->_attributes[$name] = $value;
    }

    public function toArray()
    {
        return $this->_attributes;
    }

    protected static function toObject(BaseModel $object, array $data = [])
    {
        foreach ($data as $name => $value) {
            $object->__set($name, $value);
        }
    }
}
<?php
namespace yii\swoole\db;

use Yii;

class ActiveRecord extends \yii\db\ActiveRecord
{
    /*
    * 处理标识
    */
    const ACTION_NEXT = 1;
    const ACTION_RETURN = 2;
    const ACTION_RETURN_COMMIT = 3;

    public $realation = [];

    public static function addQuery(&$query, $alias)
    {
    }

    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }


    /*
     * 查询前
     */

    public function before_AIndex($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 查询后
     */

    public function after_AIndex($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
    * 查询前
    */

    public function before_AView($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 查询后
     */

    public function after_AView($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 新建前
     */

    public function before_ACreate($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    public function before_BCreate($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 新建后
     */

    public function after_ACreate($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 更新前
     */

    public function before_AUpdate($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    public function before_BUpdate($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 更新后
     */

    public function after_AUpdate($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 删除前
     */

    public function before_ADelete($body, $class = null)
    {
        return $this->baWork($body, $class);
    }

    /*
     * 删除后
     */

    public function after_ADelete($body, $class = null)
    {
        return $this->baWork($body, $class);
    }
}
<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\db\mysql;

use yii\base\InvalidCallException;
use yii\swoole\helpers\CoroHelper;

/**
 * Schema is the class for retrieving metadata from a MySQL database (version 4.1.x and 5.x).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Schema extends \yii\db\mysql\Schema
{
    public function getLastInsertID($sequenceName = '')
    {
        $id = CoroHelper::getId();
        if ($this->db->isActive) {
            $insertId = $this->db->insertId[$id];
            unset($this->db->insertId[$id]);
            return $insertId;
        } else {
            throw new InvalidCallException('DB Connection is not active.');
        }
    }
}

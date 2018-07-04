<?php

namespace yii\swoole\mysql;

use Yii;
use yii\base\BaseObject;
use yii\db\Exception;
use yii\swoole\base\Output;
use yii\web\ServerErrorHttpException;

class Statement extends BaseObject
{
    /**
     * @var Connection
     */
    public $db;
    private $sql;
    private $pdo;
    private $params = [];
    private $mode;
    private $data;

    public function setFetchMode($mode)
    {
        $this->mode = $mode;
    }

    public function prepare($sql, $pdo)
    {
        $this->sql = $sql;
        $this->pdo = $pdo;
    }

    public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR)
    {
        $this->params[$parameter] = $value;
    }

    public function execute($timeout = 10)
    {
        try {
            $values = [];
            foreach ($this->params as $name => $value) {
                $this->sql = preg_replace('/' . $name . '/', '?', $this->sql, 1);
                $values[] = $value;
            }

            $statement = $this->pdo->prepare($this->sql);
            if (!$statement instanceof \Swoole\Coroutine\MySQL\Statement) {
                throw new Exception($this->pdo->error);
            } else {
                $this->data = $statement->execute($values, $timeout);
                if ($this->data === false) {
                    throw new Exception($this->pdo->error);
                }
            }
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->db->release();
        }
    }

    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        if (!is_array($this->data)) {
            return [];
        }
        $result = [];
        switch ($fetch_style) {
            case \PDO::FETCH_ASSOC:
                $result = array_shift($this->data);
                break;
            default:
                $result = $this->data;
        }
        return $result;
    }

    public function fetchColumn($column_number = 0)
    {
        if (!is_array($this->data)) {
            return [];
        }
        $val = array_shift($this->data);
        return $this->getColumn($val);
    }

    private function getColumn($data, $column_number = 0)
    {
        $i = 0;
        foreach ($data as $key => $v) {
            if ($i === $column_number) {
                return $v;
            }
            $i++;
        }
        return $v;
    }

    public function fetchObject($class_name = "stdClass", array $ctor_args = array())
    {
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, array $ctor_args = array())
    {
        if (!is_array($this->data)) {
            return [];
        }
        $result = [];
        switch ($fetch_style) {
            case \PDO::FETCH_COLUMN:
                foreach ($this->data as $data) {
                    $result[] = $this->getColumn($data);
                }
                break;
            default:
                $result = $this->data;
        }
        return $result;
    }

    public function nextRowset()
    {
    }

    public function closeCursor()
    {

    }

    public function rowCount()
    {
        return $this->pdo->affected_rows;
    }

    public function columnCount()
    {
    }
}
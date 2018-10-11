<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/11
 * Time: 10:05
 */

namespace yii\swoole\helpers;

use http\Exception\RuntimeException;
use Yii;
use yii\base\Component;

class SnowflakeHelper extends Component
{
    /**
     * @var int
     */
    public $machineId;

    /**
     * @var int
     */
    public $epoch = 1288834974657;

    const TYPE_DB = 0;
    const TYPE_TRACE = 1;

    /**
     * 预留2位做类型区分
     */
    const MAX_MACHINEID = 1020;


    public function init()
    {
        if ($this->machineId > self::MAX_MACHINEID || $this->machineId < 0) {
            $this->machineId = rand(0, self::MAX_MACHINEID);
        }
        Yii::$app->cache->set('lastTimestamp', -1);
    }

    public function nextId(int $mId = self::TYPE_DB): int
    {
        $mId = ($this->machineId << 2) + $mId;
        /*
        * Time - 42 bits
        */
        $time = floor(microtime(true) * 1000);

        $lastTimestamp = Yii::$app->cache->get('lastTimestamp');

        if ($time < $lastTimestamp) {
            $time = $lastTimestamp;
        }

        if ($lastTimestamp === $time) {
            $sequence = Yii::$server->atomic->add() & 4095;
            if ($sequence === 0) {
                Yii::$server->atomic->set(0);
                $time = $this->tilNextMillis($lastTimestamp);
            }
        } else {
            Yii::$server->atomic->set(0);
            $sequence = 0;
        }

        Yii::$app->cache->set('lastTimestamp', $time);

        /*
        * Substract custom epoch from current time
        */
        $time -= $this->epoch;

        /*
        * Create a base and add time to it
        */
        $bit1 = str_pad(decbin($time), 41, '0', STR_PAD_LEFT);


        /*
        * Configured machineId id - 5 bits - up to 1023 machineId
        */
        $bit2 = str_pad(decbin($mId), 10, '0', STR_PAD_LEFT);

        /*
        * sequence number - 12 bits - up to 4096 random numbers per machine
        */
        $bit3 = str_pad(decbin($sequence), 12, "0", STR_PAD_LEFT);

        /*
        * Pack
        */
        $base = $bit1 . $bit2 . $bit3;

        /*
        * Return unique time id no
        */
        return bindec($base);
    }

    private function tilNextMillis(float $lastTimestamp): float
    {
        $time = floor(microtime(true) * 1000);
        while ($time <= $lastTimestamp) {
            \Co::sleep(1);
            $time = floor(microtime(true) * 1000);
        }
        return $time;
    }
}
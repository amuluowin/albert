<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午1:06
 */

namespace yii\swoole\governance\trace;


use yii\swoole\base\Model;

class TraceModel extends Model
{
    /**
     * @var string
     */
    public $traceId = '';

    /**
     * @var int
     */
    public $parentId;

    /**
     * @var int
     */
    public $spanId;

    /**
     * @var int
     */
    public $sendTime;

    /**
     * @var string
     */
    public $sendIp;

    /**
     * @var string
     */
    public $service;

    /**
     * @var string
     */
    public $route;

    /**
     * @var string
     */
    public $method;

    public $params;

    /**
     * @var bool
     */
    public $fastCall = false;

}
<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-02
 * Time: 22:45
 */

namespace yii\swoole\consul;

use Yii;

trait ConsulTrait
{
    /**
     * @var ConsulClient
     */
    protected $client;

    public function init()
    {
        if (!$this->client instanceof ConsulClient) {
            $this->client = Yii::$app->consul;
        }
    }
}
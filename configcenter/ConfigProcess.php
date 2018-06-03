<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-02
 * Time: 23:40
 */

namespace yii\swoole\configcenter;

use Yii;
use yii\swoole\process\BaseProcess;

class ConfigProcess extends BaseProcess
{
    /**
     * @var int
     */
    public $ticket = 1;

    /**
     * @var ConfigInterface
     */
    public $client;

    public function init()
    {
        parent::init();
        if (!$this->client instanceof ConfigInterface) {
            $this->client = Yii::$app->csconf;
        }
    }

    public function start()
    {
        swoole_timer_tick($this->ticket * 1000, function () {
            $configs = $this->client->getConfig(APP_NAME);
        });
    }
}
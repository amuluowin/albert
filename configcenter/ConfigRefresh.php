<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-03
 * Time: 21:53
 */

namespace yii\swoole\configcenter;

use Yii;
use yii\base\Component;
use yii\swoole\base\BootInterface;
use yii\swoole\server\Server;

class ConfigRefresh extends Component implements BootInterface
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
        if (!$this->client instanceof ConfigInterface) {
            $this->client = Yii::$app->csconf;
        }
    }

    public function handle(Server $server = null)
    {
        swoole_timer_tick($this->ticket * 1000, function () {
            foreach (Yii::$confKeys as $key => $callBack) {
                $configs = $this->client->getConfig($key);
                call_user_func_array($callBack, [$key, $configs[$key]]);
            }
        });
    }
}
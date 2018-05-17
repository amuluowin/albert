<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午9:57
 */

namespace yii\swoole\tcp;

use Yii;
use yii\base\Component;
use yii\swoole\base\BootInterface;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\server\Server;

class BootRpcServer extends Component implements BootInterface
{
    /**
     * @var array
     */
    public $listen = [];

    /**
     * @var array
     */
    private $schme = [];

    public function handle(Server $server)
    {
        foreach ($this->listen as $schme => $data) {
            list($type, $on) = $data;
            $config = ArrayHelper::getValue(Yii::$app->params['swoole'], $schme, []);
            if ($config) {
                if ($type) {
                    $this->schme[$schme] = $server->server->listen($config['host'], $config['port'], $type);
                } else {
                    $this->schme[$schme] = $server->server->listen($config['host'], $config['port']);
                }
                foreach ($on as $bind => $method) {
                    $this->schme[$schme]->on($bind, [$server, $method]);
                }
                $this->schme[$schme]->set($config['server']);
            }
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-23
 * Time: 下午5:45
 */

namespace yii\swoole\base;

use Yii;
use yii\base\Component;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\server\Server;

class AddListener extends Component implements BootInterface
{
    /**
     * @var array
     */
    public $listen = [];

    /**
     * @var array
     */
    private $schme = [];

    public function handle(Server $server = null)
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
                    $this->schme[$schme]->on($bind, $method);
                }
                $this->schme[$schme]->set($config['server']);
                print_r(sprintf('listen %8s %s:%d' . PHP_EOL, $schme, $config['host'], $config['port']));
            }
        }
    }
}
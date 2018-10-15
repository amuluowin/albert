<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/12
 * Time: 0:59
 */

namespace yii\swoole\seaslog;


use yii\base\Component;
use yii\swoole\base\BootInterface;
use yii\swoole\server\Server;

class LogBufferflush extends Component implements BootInterface
{
    /**
     * @var int
     */
    public $ticket = 1;

    public function handle(Server $server = null)
    {
        if (!$server->server->taskworker) {
            \Swoole\Timer::tick($this->ticket * 1000, function (int $tick_id) {
                \Seaslog::flushBuffer();
            });
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-2-27
 * Time: 下午5:10
 */

namespace yii\swoole\process;

use Yii;
use yii\swoole\helpers\SerializeHelper;

class MysqlProcess extends BaseProcess
{
    public function init()
    {
        $this->server = Yii::$app->getSwooleServer();
    }

    public function start($class = null, $config = null)
    {
        $mysqlprocess = new \swoole_process(function ($process) {
            $process->name('swoole-MYSQL');
            swoole_event_add($process->pipe, function ($pipe) use ($process) {
                $recv = SerializeHelper::unserialize($process->read());
                $worker_id = $recv['worker_id'];
                $data = $recv['data'];
                unset($recv);
                go(function () use ($data, $worker_id) {
                    $result = Yii::$app->db->createCommand($data)->execute();
                    //send data to master
                    $this->server->sendMessage(SerializeHelper::serialize($result), $worker_id);
                });
            });
        }, false, 2);

        $this->server->addProcess($mysqlprocess);
        $this->server->mysqlprocess = $mysqlprocess;
    }
}
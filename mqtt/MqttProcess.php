<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-25
 * Time: 下午9:06
 */

namespace yii\swoole\mqtt;


use yii\swoole\helpers\SerializeHelper;
use yii\swoole\process\BaseProcess;

class MqttProcess extends BaseProcess
{
    /**
     * @var MqttClientCtrl
     */
    public $client;

    public function start()
    {
        for ($i = 0; $i < $this->processList; $i++) {
            $process = new \swoole_process(function ($process) use ($i) {
                $process->name('swoole-' . $this->name . '-' . $i);
                if (!in_array($process->pid, $this->pids)) {
                    $this->pids[] = $process->pid;
                }
                swoole_event_add($process->pipe, function ($pipe) use ($process) {
                    $recv = $process->read();
                    $recv = SerializeHelper::unserialize($recv);
                    list($method, $data) = $recv;
                    $this->client->$method(...$data);
                });
            }, $this->inout, $this->pipe);
            $this->saveProcess($process);
        }
        $this->savePid();
    }
}
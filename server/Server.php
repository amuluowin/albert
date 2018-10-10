<?php

namespace yii\swoole\server;

use swoole_server;
use Yii;
use yii\base\Component;
use yii\swoole\async\Task;
use yii\swoole\base\BootInterface;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\shmcache\Cache;

/**
 * 基础的server实现
 *
 * @package yii\swoole\server
 */
abstract class Server extends Component
{

    use WorkTrait;
    /**
     * @var  服务器名称
     */
    public $name = 'swoole-server';

    /**
     * @var  进程文件路径
     */
    public $pidFile;

    /**
     * @var swoole_server
     */
    public $server;

    /**
     * @var array 当前配置文件
     */
    public $config = [];

    /**
     * @var string
     */
    public $root;

    /**
     * @var array
     */
    public $schme = [];

    /**
     * @var array
     */
    protected $webConfig = [];

    /**
     * 设置进程标题
     *
     * @param  $name
     */
    protected function setProcessTitle($name)
    {
        if (function_exists('swoole_set_process_name')) {
            @swoole_set_process_name($name);
        } else {
            @cli_set_process_title($name);
        }
    }

    protected function beforeStart(array $beforeStart = null)
    {
        Yii::$server = $this->server;
        $beforeStart = $beforeStart ?: Yii::$app->beforeStart;
        foreach ($beforeStart as $handle) {
            if (!$handle instanceof BootInterface) {
                if (!key_exists('class', $handle)) {
                    $this->beforeStart($handle);
                } else {
                    $handle = Yii::createObject($handle);
                    $handle->handle($this);
                }
            }
        }
    }

    public function stop()
    {
        if ($this->pidFile) {
            $pid = file_get_contents($this->pidFile);
            \swoole_process::kill(intval($pid));
        }
    }

    /**
     * @param $server
     */
    public function onStart($server)
    {
        $this->setProcessTitle($this->name . ': master');
        if ($this->pidFile) {
            file_put_contents($this->pidFile, $server->master_pid);
        }
        if (ArrayHelper::getValue(Yii::$app->params, 'auto_clear_cache') && Yii::$app->cache instanceof Cache) {
            Yii::$app->cache->flush();
        }
    }

    public function onShutdown($server)
    {
        if ($this->pidFile) {
            unlink($this->pidFile);
        }
    }

    public function onWorkerStart($server, $worker_id)
    {
        if (!$server->taskworker) {
            //worker
            $this->setProcessTitle($this->name . ': worker' . ": {$worker_id}");
        } else {
            //task
            $this->setProcessTitle($this->name . ': task' . ": {$worker_id}");
        }
        $this->workerStart($server, $worker_id);
    }

    public function onWorkerStop($server, $worker_id)
    {
        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }

    public function onConnect($server, $fd, $from_id)
    {

    }

    public function onReceive($server, $fd, $from_id, $data)
    {

    }

    public function onPacket($server, $data, array $client_info)
    {

    }

    public function onClose($server, $fd, $from_id)
    {

    }

    /**
     * 处理异步任务
     *
     * @param swoole_server $serv
     * @param mixed $task_id
     * @param mixed $from_id
     * @param string $data
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
        return Task::runTask($data, $task_id);
    }

    /**
     * 处理异步任务的结果
     *
     * @param swoole_server $serv
     * @param mixed $task_id
     * @param string $data
     */
    public function onFinish($serv, $task_id, $data)
    {
        return $data;
    }

    public function onPipeMessage($server, $from_worker_id, $message)
    {

    }

    public function onWorkerError($serv, $worker_id, $worker_pid, $exit_code)
    {

    }

    /**
     * @param $server
     */
    public function onManagerStart($server)
    {
        $this->setProcessTitle($this->name . ': manager');
    }

    public function onManagerStop($serv)
    {

    }
}

<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午10:27
 */

namespace yii\swoole\rpc;

use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\BaseJson;
use yii\swoole\pack\TcpPack;

trait RpcTrait
{
    public function onReceive($serv, $fd, $from_id, $data)
    {
        $data = TcpPack::decode($data, 'rpc');
        $this->run($serv, $fd, $from_id, $data);
    }

    public function run($serv, $fd, $form_id, $data)
    {
        if ($data['fastCall']) {
            $serv->send($fd, TcpPack::encode(['status' => 200, 'code' => 0, 'message' => 'success', 'data' => null], 'rpc'));
        }

        $function = Yii::$app->RpcHelper->getCurCall($data['service'], $data['route'], $data['method']);

        if (is_string($function) && strpos($function, '\\') === false && strpos($function, '/') !== false) {
            try {
                list($query, $params) = $data['params'];
                Yii::$app->request->setBodyParams($params);
                Yii::$app->request->setHostInfo(null);
                Yii::$app->request->setUrl($function);
                Yii::$app->request->setRawBody(json_encode($params));
                Yii::$app->request->setTraceId(isset($data['traceId']) ? $data['traceId'] : null);
                Yii::$app->getRequest()->setQueryParams($query);
                Yii::$app->refresh();
                $result = Yii::$app->runAction($function, $query);
                $serv->send($fd, TcpPack::encode($result, 'rpc'));
                $this->setLog($result);
            } catch (\Exception $e) {
                $serv->send($fd, TcpPack::encode(Yii::$app->getErrorHandler()->converter($e, 'convertExceptionToArray'), 'rpc'));
                $this->setLog($e);
            }

        } elseif (is_array($function)) {
            try {
                $pnum = count($function);
                if ($pnum === 3) {
                    list($comp, $obj, $method) = $function;
                    if (Yii::$app->has($comp)) {
                        $obj = Yii::$app->get($comp)->$obj;
                        $result = $obj->{$method}(...$data['params']);
                    } else {
                        $result = new InvalidArgumentException('Error send data!');
                    }
                } elseif ($pnum === 2) {
                    list($obj, $method) = $function;
                    if (Yii::$app->has($obj)) {
                        $obj = Yii::$app->get($obj);
                        $result = $obj->{$method}(...$data['params']);
                    } else {
                        $result = call_user_func_array($function, [$data['params']]);
                    }
                } else {
                    $result = new InvalidArgumentException('Error send data!');
                }

                $serv->send($fd, TcpPack::encode($result, 'rpc'));
                $this->setLog($result);
            } catch (\Exception $e) {
                $serv->send($fd, TcpPack::encode(null, 'rpc'));
                $this->setLog($e);
            }
        }
    }

    private function setLog($result)
    {
        Yii::$app->response->content = BaseJson::encode(Yii::createObject('yii\swoole\rest\Serializer')->serialize($result));
        Yii::getLogger()->flush(true);
        Yii::$app->release();
    }
}
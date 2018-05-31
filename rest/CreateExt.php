<?php

namespace yii\swoole\rest;

use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class CreateExt extends \yii\base\Object
{

    public static function actionDo($model, $body, $transaction = null)
    {
        $transaction = $transaction ? $transaction : $model->getDb()->beginTransaction();
        try {
            if (isset($body["batch"])) {
                $scenes = ArrayHelper::remove($filter, 'beforeCreate','');
                if (in_array($scenes, $model->sceneList)) {
                    list($status, $filter) = $model->$scenes($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $result = $model::getDb()->insertSeveral($model, $body['batch']);
                $scenes = ArrayHelper::remove($filter, 'afertCreate','');
                if (in_array($scenes, $model->sceneList)) {
                    list($status, $filter) = $model->$scenes($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
            } elseif (isset($body["batchMTC"])) {
                $result = [];
                foreach ($body["batchMTC"] as $params) {
                    $res = self::createSeveral(clone $model, $params, $transaction);
                    if ($res instanceof ResponeModel) {
                        return $res;
                    }
                    $result[] = $res;
                }
            } else {
                $result = self::createSeveral($model, $body, $transaction);
            }
            //
            if ($transaction->getIsActive()) {
                $transaction->commit();
            }
        } catch (\Exception $ex) {
            $transaction->rollBack();
            throw $ex;
        }

        return $result;
    }

    public static function createSeveral($model, $body, $transaction)
    {
        $model->load($body, '');
        $scenes = ArrayHelper::remove($filter, 'beforeCreate','');
        if (in_array($scenes, $model->sceneList)) {
            list($status, $filter) = $model->$scenes($filter);
            if ($status >= $model::ACTION_RETURN) {
                return $filter;
            }
        }
        yii::$app->BaseHelper->validate($model, $transaction);
        if ($model->save(false)) {
            $scenes = ArrayHelper::remove($filter, 'afterCreate','');
            if (in_array($scenes, $model->sceneList)) {
                list($status, $filter) = $model->$scenes($filter);
                if ($status >= $model::ACTION_RETURN) {
                    return $filter;
                }
            }
            $model = self::saveRealation($model, $body, $transaction);
            if ($model instanceof ResponeModel) {
                return $model;
            }
        } elseif (!$model->hasErrors()) {
            $transaction->rollBack();
            throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
        }

        return $model;
    }

    private static function saveRealation($model, $body, $transaction)
    {
        $result = [];
        //关联模型
        if (isset($model->realation)) {
            foreach ($model->realation as $key => $val) {
                if (isset($body[$key])) {
                    $child = $model->getRelation($key)->modelClass;
                    if ($body[$key]) {
                        if ((bool)count(array_filter(array_keys($body[$key]), 'is_string'))) {
                            $body[$key] = [$body[$key]];
                        }
                        foreach ($body[$key] as $params) {
                            if ($val) {
                                foreach ($val as $c_attr => $p_attr) {
                                    $params[$c_attr] = $model->{$p_attr};
                                }
                            }
                            $child_model = new $child();
                            $res = self::createSeveral($child_model, $params, $transaction);
                            if ($res instanceof ResponeModel) {
                                return $res;
                            }
                            $result[$key][] = $res;
                        }
                    }
                }
            }
        }
        $model = $model->toArray();
        foreach ($result as $key => $val) {
            $model[$key] = $val;
        }
        return $model;
    }

}

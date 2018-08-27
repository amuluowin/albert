<?php

namespace yii\swoole\rest;

use Yii;
use yii\base\InvalidArgumentException;
use yii\swoole\db\DBHelper;
use yii\swoole\db\Query;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class UpdateExt extends \yii\base\Object
{

    public static function actionDo($model, $body, $transaction = null)
    {
        $transaction = $transaction ? $transaction : $model->getDb()->beginTransaction();
        if ($body) {
            $bscenes = ArrayHelper::remove($filter, 'beforeUpdate', '');
            $ascenes = ArrayHelper::remove($filter, 'afterUpdate', '');
            if (isset($body['batch'])) {
                if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                    list($status, $filter) = $model->{$model->sceneList[$bscenes]}($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $result = $model::getDb()->updateSeveral($model, $body['batch']);

                if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                    list($status, $filter) = $model->{$model->sceneList[$ascenes]}($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
            } elseif (isset($body['batchMTC'])) {
                $result = [];
                foreach ($body['batchMTC'] as $params) {
                    $res = self::updateSeveral(clone $model, $params, $transaction);
                    if ($res instanceof ResponeModel) {
                        return $res;
                    }
                    $result[] = $res;
                }
            } elseif (isset($body['condition']) && $body['condition']) {
                $condition = DBHelper::Search((new Query()), $body['condition'])->where;
                $result = $model->updateAll($body['edit'], $condition);
                if ($result === false) {
                    if ($transaction) {
                        $transaction->rollBack();
                    }
                    throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
                }
            } else {
                if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                    list($status, $filter) = $model->{$model->sceneList[$bscenes]}($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $result = self::updateSeveral($model, $body, $transaction);


                if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                    list($status, $filter) = $model->{$model->sceneList[$ascenes]}($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
            }
        } else {
            if ($transaction) {
                $transaction->rollBack();
            }
            throw new InvalidArgumentException('缺少参数!');
        }

        if ($transaction->getIsActive()) {
            $transaction->commit();
        }

        return $result;
    }

    /*
     * 批量实体模型保存
     */

    public static function updateSeveral($model, $body, $transaction)
    {
        /* @var $model ActiveRecord */
        try {
            $keys = $model::primaryKey();
            $values = null;
            foreach ($keys as $value) {
                if (array_key_exists($value, $body)) {
                    $values[] = $body[$value];
                }
            }
            if ($values !== null && count($keys) === count($values)) {
                $exit = $model::findOne(array_combine($keys, $values));
                if ($exit) {
                    $model = $exit;
                }
            }
            $bscenes = ArrayHelper::remove($filter, 'beforeUpdate', '');
            $ascenes = ArrayHelper::remove($filter, 'afterUpdate', '');
            if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                list($status, $filter) = $model->{$model->sceneList[$bscenes]}($filter);
                if ($status >= $model::ACTION_RETURN) {
                    return $filter;
                }
            }

            $model->load($body, '');
            Yii::$app->BaseHelper->validate($model, $transaction);
            if ($model->save(false) === false && !$model->hasErrors()) {
                throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
            } else {
                if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                    list($status, $filter) = $model->{$model->sceneList[$ascenes]}($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $model = self::saveRealation($model, $body, $transaction);
                if ($model instanceof ResponeModel) {
                    return $model;
                }
            }
            return $model;
        } catch (\Exception $ex) {
            $transaction->rollBack();
            throw $ex;
        }
    }

    /*
     * 保存关联模型
     */

    public static function saveRealation($model, $body, $transaction)
    {
        $result = [];
        //关联模型
        if (isset($model->realation)) {
            foreach ($model->realation as $key => $val) {
                if (isset($body[$key])) {
                    $child = $model->getRelation($key)->modelClass;
                    if ($body[$key]) {

                        if ((bool)count(array_filter(array_keys($body[$key]), 'is_string'))) {
                            $params = [$body[$key]];
                        } else {
                            $params = $body[$key];
                        }
                        if (isset($params['edit']) && $params['edit']) {
                            $child_model = new $child();
                            self::actionDo($child_model, $params, $transaction);
                        } else {
                            $keys = $child::primaryKey();
                            foreach ($params as $param) {
                                if ($val) {
                                    $child_model = new $child();
                                    $child_id = key($val);
                                    $values = null;
                                    $existModel = null;
                                    foreach ($keys as $value) {
                                        if (array_key_exists($value, $param)) {
                                            $values[] = $param[$value];
                                        }
                                    }
                                    if ($values !== null && count($keys) === count($values)) {
                                        $existModel = $child_model::find()->where([$child_id => $model[$val[$child_id]]])->andWhere(array_combine($keys, $values))->exists();
                                    }
                                    $child_model->isNewRecord = !$existModel;
                                    foreach ($val as $c_attr => $p_attr) {
                                        $param[$c_attr] = $model->{$p_attr};
                                    }
                                    $res = self::updateSeveral($child_model, $param, $transaction);
                                    if ($res instanceof ResponeModel) {
                                        return $res;
                                    }
                                    $result[$key][] = $res;
                                }
                            }
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

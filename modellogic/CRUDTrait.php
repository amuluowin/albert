<?php

namespace yii\swoole\modellogic;

use Yii;
use yii\swoole\db\DBHelper;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\rest\CreateExt;
use yii\swoole\rest\DeleteExt;
use yii\swoole\rest\IndexExt;
use yii\swoole\rest\ResponeModel;
use yii\swoole\rest\UpdateExt;

trait CRUDTrait
{
    public static function findModel($id, $modelClass = null)
    {
        $keys = $modelClass::primaryKey();
        if (count($keys) > 1) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                $model = $modelClass::findOne(array_combine($keys, $values));
            }
        } elseif ($id !== null) {
            $model = $modelClass::findOne($id);
        }

        if (isset($model)) {
            return $model;
        } else {
            throw new NotFoundHttpException("Object not found: $id");
        }
    }

    public static function View($filter, $id, $modelClass = null)
    {
        $modelClass = ($modelClass ?: static::$modelClass);
        $modelClass = new $modelClass();
        $bscenes = ArrayHelper::remove($filter, 'beforeView', '');
        $ascenes = ArrayHelper::remove($filter, 'afterView', '');
        if (key_exists($bscenes, $modelClass->sceneList) && method_exists($modelClass, $modelClass->sceneList[$bscenes])) {
            list($status, $filter) = $modelClass->{$modelClass->sceneList[$bscenes]}($filter);
            if ($status >= $modelClass::ACTION_RETURN) {
                return $filter;
            }
        }
        $keys = $modelClass::primaryKey();
        foreach ($keys as $index => $key) {
            $keys[$index] = $modelClass::tableName() . '.' . $key;
        }
        if (count($keys) > 1 && $id !== null) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                $model = $filter ? DBHelper::Search($modelClass::find()->where(array_combine($keys, $values)), $filter)->asArray()->one() : $modelClass::findOne(array_combine($keys, $values));
            }
        } elseif ($id !== null) {
            $model = $filter ? DBHelper::Search($modelClass::find()->where(array_combine($keys, [$id])), $filter)->asArray()->one() : $modelClass::findOne($id);
        } elseif ($filter) {
            $model = DBHelper::Search($modelClass::find(), $filter)->asArray()->one();
        }

        if (isset($model)) {
            return $model;
        } else {
            throw new NotFoundHttpException("Object not found: $id");
        }
    }

    public static function Index($filter = null, $page = 0, $modelClass = null)
    {
        $modelClass = ($modelClass ?: static::$modelClass);
        $modelClass = new $modelClass();
        return IndexExt::actionDo($modelClass, $filter, $page);
    }

    public static function Create($body, $modelClass = null)
    {
        $modelClass = ($modelClass ? $modelClass : static::$modelClass);
        $modelClass = new $modelClass();

        return CreateExt::actionDo($modelClass, $body);
    }

    public static function Update($body, $id = null, $modelClass = null)
    {
        $modelClass = ($modelClass ? $modelClass : static::$modelClass);
        if ($id) {
            /* @var $model ActiveRecord */
            $model = self::findModel($id, $modelClass);

            $transaction = $model->getDb()->beginTransaction();
            try {
                $bscenes = ArrayHelper::remove($body, 'beforeUpdate', '');
                $ascenes = ArrayHelper::remove($body, 'afterUpdate', '');
                if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                    list($status, $body) = $model->{$model->sceneList[$bscenes]}($body);
                    if ($status >= $model::ACTION_RETURN) {
                        return $body;
                    }
                }
                $model->load($body, '');
                Yii::$app->BaseHelper->validate($model, $transaction);
                if ($model->save(false) === false && !$model->hasErrors()) {
                    throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
                } else {
                    if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                        list($status, $body) = $model->{$model->sceneList[$ascenes]}($body);
                        if ($status >= $model::ACTION_RETURN) {
                            return $body;
                        }
                    }
                    $model = UpdateExt::saveRealation($model, $body, $transaction);
                    if ($model instanceof ResponeModel) {
                        return $model;
                    }
                }
                if ($transaction->getIsActive()) {
                    $transaction->commit();
                }
                return $model;
            } catch (\Exception $ex) {
                $transaction->rollBack();
                throw $ex;
            }
        } else {
            $modelClass = new $modelClass();
            return UpdateExt::actionDo($modelClass, $body);
        }
    }

    public static function Delete($body = null, $id = null, $modelClass = null)
    {
        $modelClass = ($modelClass ? $modelClass : static::$modelClass);
        if ($id) {
            $model = self::findModel($id, $modelClass);

            $bscenes = ArrayHelper::remove($body, 'beforeDelete', '');
            $ascenes = ArrayHelper::remove($body, 'afterDelete', '');
            if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                list($status, $body) = $model->{$model->sceneList[$bscenes]}($body);
                if ($status >= $model::ACTION_RETURN) {
                    return $body;
                }
            }

            if ($model->delete() === false) {
                throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
            }

            if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                list($status, $body) = $model->{$model->sceneList[$ascenes]}($body);
                if ($status >= $model::ACTION_RETURN) {
                    return $body;
                }
            }

            return $model;
        } else {
            $modelClass = ($modelClass ? $modelClass : static::$modelClass);
            $modelClass = new $modelClass();
            $result = DeleteExt::actionDo($modelClass, $body);
        }
        return $result;
    }
}
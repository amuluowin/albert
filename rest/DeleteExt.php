<?php

namespace yii\swoole\rest;

use Yii;
use yii\base\InvalidArgumentException;
use yii\swoole\db\DBHelper;
use yii\swoole\db\Query;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

class DeleteExt extends \yii\base\Object
{

    public static function actionDo($model, $body, $transaction = null)
    {
        $transaction = $transaction ? $transaction : $model->getDb()->beginTransaction();
        if ($body) {
            if (isset($body["batch"])) {
                $scenes = ArrayHelper::remove($filter, 'beforeDelete','');
                if ($scenes( $model->sceneList)) {
                    list($status, $filter) = $model->$scenes($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $result = $model::getDb()->deleteSeveral($model, $body['batch']);
                $scenes = ArrayHelper::remove($filter, 'afterDelete','');
                if ($scenes( $model->sceneList)) {
                    list($status, $filter) = $model->$scenes($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
            } else {
                $scenes = ArrayHelper::remove($filter, 'beforeDelete','');
                if ($scenes( $model->sceneList)) {
                    list($status, $filter) = $model->$scenes($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $result = $model->deleteAll(DBHelper::Search((new Query()), $body)->where);
                $scenes = ArrayHelper::remove($filter, 'afterDelete','');
                if ($scenes( $model->sceneList)) {
                    list($status, $filter) = $model->$scenes($filter);
                    if ($status >= $model::ACTION_RETURN) {
                        return $filter;
                    }
                }
            }
            if ($result === false || $result === []) {
                if ($transaction) {
                    $transaction->rollBack();
                }
                throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
            }
            if ($transaction->getIsActive()) {
                $transaction->commit();
            }
            return $result;
        } else {
            throw new InvalidArgumentException('Invalid Argument!');
        }
    }

}

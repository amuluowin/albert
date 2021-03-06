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
                $bscenes = ArrayHelper::remove($body, 'beforeDelete', '');
                $ascenes = ArrayHelper::remove($body, 'afterDelete', '');
                if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                    list($status, $body) = $model->{$model->sceneList[$bscenes]}($body);
                    if ($status >= $model::ACTION_RETURN) {
                        return $body;
                    }
                }
                $result = $model::getDb()->deleteSeveral($model, $body['batch']);
                if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                    list($status, $body) = $model->{$model->sceneList[$ascenes]}($body);
                    if ($status >= $model::ACTION_RETURN) {
                        return $body;
                    }
                }
            } else {
                $bscenes = ArrayHelper::remove($body, 'beforeDelete', '');
                $ascenes = ArrayHelper::remove($body, 'afterDelete', '');
                if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
                    list($status, $body) = $model->{$model->sceneList[$bscenes]}($body);
                    if ($status >= $model::ACTION_RETURN) {
                        return $body;
                    }
                }
                $result = $model->deleteAll(DBHelper::Search((new Query()), $body)->where);

                if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
                    list($status, $body) = $model->{$model->sceneList[$ascenes]}($body);
                    if ($status >= $model::ACTION_RETURN) {
                        return $body;
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

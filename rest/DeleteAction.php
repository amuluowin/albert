<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\rest;

use Yii;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

/**
 * DeleteAction implements the API endpoint for deleting a model.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DeleteAction extends Action
{

    /**
     * Deletes a model.
     * @param mixed $id id of the model to be deleted.
     * @throws ServerErrorHttpException on failure.
     */
    public function run($id = null)
    {
        $body = Yii::$app->getRequest()->getBodyParams();
        if (is_array($this->modelClass)) {
            list($service, $route) = $this->modelClass;
            return Yii::$app->rpc->call($service, $route)->Delete($body, $id);
        }
        if ($id) {
            $model = $this->findModel($id);

            if ($this->checkAccess) {
                call_user_func($this->checkAccess, $this->id, $model);
            }
            $bscenes = ArrayHelper::remove($filter, 'beforeDelete', '');
            $ascenes = ArrayHelper::remove($filter, 'afterDelete', '');
            if (key_exists($bscenes, $modelClass->sceneList) && method_exists($modelClass, $modelClass->sceneList[$bscenes])) {
                list($status, $filter) = $modelClass->{$modelClass->sceneList[$bscenes]}($filter);
                if ($status >= $modelClass::ACTION_RETURN) {
                    return $filter;
                }
            }

            if ($model->delete() === false) {
                throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
            }

            if (key_exists($ascenes, $modelClass->sceneList) && method_exists($modelClass, $modelClass->sceneList[$ascenes])) {
                list($status, $filter) = $modelClass->{$modelClass->sceneList[$ascenes]}($filter);
                if ($status >= $modelClass::ACTION_RETURN) {
                    return $filter;
                }
            }

            return $model;
        } else {
            if ($this->checkAccess) {
                call_user_func($this->checkAccess, $this->id);
            }
            $modelClass = new $this->modelClass();
            $filter = Yii::$app->getRequest()->getBodyParams();
            $result = DeleteExt::actionDo($modelClass, $filter);
        }
        return $result;
    }

}

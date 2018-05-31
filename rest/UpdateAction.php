<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\rest;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\swoole\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

/**
 * UpdateAction implements the API endpoint for updating a model.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class UpdateAction extends Action
{

    /**
     * @var string the scenario to be assigned to the model before it is validated and updated.
     */
    public $scenario = Model::SCENARIO_DEFAULT;

    /**
     * Updates an existing model.
     * @param string $id the primary key of the model.
     * @return \yii\swoole\db\ActiveRecordInterface the model being updated
     * @throws ServerErrorHttpException if there is any error when updating the model
     */
    public function run($id = null)
    {
        $body = Yii::$app->getRequest()->getBodyParams();
        if (is_array($this->modelClass)) {
            list($service, $route) = $this->modelClass;
            return Yii::$app->rpc->call($service, $route)->Update($body, $id);
        }
        if ($id) {
            /* @var $model ActiveRecord */
            $model = $this->findModel($id);

            if ($this->checkAccess) {
                call_user_func($this->checkAccess, $this->id, $model);
            }

            $model->scenario = $this->scenario;
            $transaction = $model->getDb()->beginTransaction();
            try {
                $scenes = ArrayHelper::remove($filter, 'beforeUpdate','');
                if ($scenes( $modelClass->sceneList)) {
                    list($status, $filter) = $modelClass->$scenes($filter);
                    if ($status >= $modelClass::ACTION_RETURN) {
                        return $filter;
                    }
                }
                $model->load($body, '');
                yii::$app->BaseHelper->validate($model, $transaction);
                if ($model->save(false) === false && !$model->hasErrors()) {
                    throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
                } else {
                    $scenes = ArrayHelper::remove($filter, 'afterUpdate','');
                    if ($scenes( $modelClass->sceneList)) {
                        list($status, $filter) = $modelClass->$scenes($filter);
                        if ($status >= $modelClass::ACTION_RETURN) {
                            return $filter;
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
            if ($this->checkAccess) {
                call_user_func($this->checkAccess, $this->id);
            }
            $modelClass = new $this->modelClass();
            $filter = Yii::$app->getRequest()->getBodyParams();
            return UpdateExt::actionDo($modelClass, $filter);
        }
    }

}

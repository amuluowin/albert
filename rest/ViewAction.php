<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\rest;

use Yii;
use yii\swoole\helpers\ArrayHelper;

/**
 * ViewAction implements the API endpoint for returning the detailed information about a model.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ViewAction extends Action
{

    /**
     * Displays a model.
     * @param string $id the primary key of the model.
     * @return \yii\swoole\db\ActiveRecordInterface the model being displayed
     */
    public function run($id = null)
    {

        $filter = Yii::$app->getRequest()->getBodyParams();
        if (is_array($this->modelClass)) {
            list($serivce, $route) = $this->modelClass;
            return Yii::$app->rpc->call($serivce, $route)->View($filter, $id);
        }
        $modelClass = new $this->modelClass();
        $bscenes = ArrayHelper::remove($filter, 'beforeView', '');
        $ascenes = ArrayHelper::remove($filter, 'afterView', '');
        if (key_exists($bscenes, $modelClass->sceneList) && method_exists($modelClass, $modelClass->sceneList[$bscenes])) {
            list($status, $filter) = $modelClass->{$modelClass->sceneList[$bscenes]}($filter);
            if ($status >= $modelClass::ACTION_RETURN) {
                return $filter;
            }
        }
        $model = $this->searchModel($filter, $id);


        if (key_exists($ascenes, $modelClass->sceneList) && method_exists($modelClass, $modelClass->sceneList[$ascenes])) {
            list($status, $filter) = $modelClass->{$modelClass->sceneList[$ascenes]}($filter);
            if ($status >= $modelClass::ACTION_RETURN) {
                return $filter;
            }
        }
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $filter, $model);
        }
        return $model;
    }

}

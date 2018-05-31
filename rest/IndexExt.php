<?php

namespace yii\swoole\rest;

use Yii;
use yii\db\Query;
use yii\swoole\db\DBHelper;
use yii\swoole\helpers\ArrayHelper;

class IndexExt
{

    public static function actionDo($model, $filter = null, $page = null)
    {
        if (!$filter) {
            $filter = Yii::$app->getRequest()->getBodyParams();
        }
        if ($page === null) {
            $page = (int)Yii::$app->request->get('page', 1) - 1;
        }

        $bscenes = ArrayHelper::remove($filter, 'beforeIndex', '');
        $ascenes = ArrayHelper::remove($filter, 'afterIndex', '');
        if (key_exists($bscenes, $model->sceneList) && method_exists($model, $model->sceneList[$bscenes])) {
            list($status, $filter) = $model->{$model->sceneList[$bscenes]}($filter);
            if ($status >= $model::ACTION_RETURN) {
                return $filter;
            }
        }
        $res = new ResponeModel();
        if ($filter instanceof Query) {
            list($total, $data) = DBHelper::SearchList($filter, [], $page);
        } else {
            list($total, $data) = DBHelper::SearchList($model::find(), $filter, $page);
        }

        if (key_exists($ascenes, $model->sceneList) && method_exists($model, $model->sceneList[$ascenes])) {
            list($status, $filter) = $model->{$model->sceneList[$ascenes]}($filter);
            if ($status >= $model::ACTION_RETURN) {
                return $filter;
            }
        }
        return $res->setModel('200', 0, 'success!', $data, ['totalCount' => $total]);
    }

}

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

        $scenes = ArrayHelper::remove($filter, 'beforeIndex','');
        if (in_array($scenes, $model->sceneList)) {
            list($status, $filter) = $model->$scenes($filter);
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
        $scenes = ArrayHelper::remove($filter, 'afterIndex','');
        if (in_array($scenes, $model->sceneList)) {
            list($status, $filter) = $model->$scenes($filter);
            if ($status >= $model::ACTION_RETURN) {
                return $filter;
            }
        }
        return $res->setModel('200', 0, 'success!', $data, ['totalCount' => $total]);
    }

}

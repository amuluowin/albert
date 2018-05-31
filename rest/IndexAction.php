<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\rest;

use Yii;
use yii\data\ActiveDataProvider;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class IndexAction extends Action
{

    /**
     * @return ActiveDataProvider
     */
    public function run($filter = null)
    {
        if (is_array($this->modelClass)) {
            if (!$filter) {
                $filter = Yii::$app->getRequest()->getBodyParams();
            }
            $page = (int)Yii::$app->request->get('page', 1) - 1;
            list($service, $route) = $this->modelClass;
            return Yii::$app->rpc->call($service, $route)->Index($filter, $page);
        }
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }
        $modelClass = new $this->modelClass();
        return IndexExt::actionDo($modelClass, \yii\helpers\Json::decode($filter));
    }

}

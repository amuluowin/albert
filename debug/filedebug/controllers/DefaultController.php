<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\debug\filedebug\controllers;

use Yii;
use yii\debug\models\search\Debug;
use yii\swoole\Application;
use yii\swoole\helpers\SerializeHelper;
use yii\web\NotFoundHttpException;

/**
 * Debugger controller
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DefaultController extends \yii\debug\controllers\DefaultController
{

    private $_manifest;

    public function actionIndex()
    {
        if (!Application::$workerApp) {
            return parent::actionIndex();
        }
        $searchModel = new Debug();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getManifest());

        // load latest request
        $tags = array_keys($this->getManifest());
        $tag = reset($tags);
        $this->loadData($tag);

        return $this->render('index', [
            'panels' => $this->module->panels,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'manifest' => $this->getManifest(),
        ]);
    }

    protected function getManifest($forceReload = false)
    {


        if ($this->_manifest === null || $forceReload) {
            if ($forceReload) {
                clearstatcache();
            }
            $indexFile = $this->module->dataPath . '/index.data';

            $content = '';
            $fp = @fopen($indexFile, 'r');
            if ($fp !== false) {
                @flock($fp, LOCK_SH);
                if (!Application::$workerApp) {
                    $content = fread($fp, filesize($indexFile));

                } else {
                    $content = \Swoole\Coroutine::fread($fp);
                }
                @flock($fp, LOCK_UN);
                fclose($fp);
            }

            if ($content !== '') {
                $this->_manifest = array_reverse(SerializeHelper::unserialize($content), true);
            } else {
                $this->_manifest = [];
            }
        }

        return $this->_manifest;
    }

    public function loadData($tag, $maxRetry = 0)
    {
        // retry loading debug data because the debug data is logged in shutdown function
        // which may be delayed in some environment if xdebug is enabled.
        // See: https://github.com/yiisoft/yii2/issues/1504
        for ($retry = 0; $retry <= $maxRetry; ++$retry) {
            $manifest = $this->getManifest($retry > 0);
            if (isset($manifest[$tag])) {
                $dataFile = $this->module->dataPath . "/$tag.data";
                if (!Application::$workerApp) {
                    $data = SerializeHelper::unserialize(file_get_contents($dataFile));
                } else {
                    $fp = @fopen($dataFile, 'r');
                    @flock($fp, LOCK_SH);
                    $data = SerializeHelper::unserialize(\Swoole\Coroutine::fread($fp));
                    @flock($fp, LOCK_UN);
                    fclose($fp);
                }
                $exceptions = $data['exceptions'];
                foreach ($this->module->panels as $id => $panel) {
                    if (isset($data[$id])) {
                        $panel->tag = $tag;
                        $panel->load(SerializeHelper::unserialize($data[$id]));
                    }
                    if (isset($exceptions[$id])) {
                        $panel->setError($exceptions[$id]);
                    }
                }
                $this->summary = $data['summary'];

                return;
            }
            \Swoole\Coroutine::sleep(1);
        }

        throw new NotFoundHttpException("Unable to find debug data tagged with '$tag'.");
    }
}

<?php

namespace yii\swoole\debug\remotedebug;

use Yii;
use yii\debug\Panel;
use yii\swoole\Application;
use yii\swoole\Refreshable;
use yii\swoole\web\View;

/**
 * Class Module
 *
 * @package yii\swoole\debug\remotedebug
 */
class Module extends \yii\debug\Module implements Refreshable
{
    public $controllerNamespace = 'yii\swoole\debug\remotedebug\controllers';

    public $controllerMap = ['default' => 'yii\swoole\debug\remotedebug\controllers\DefaultController'];

    public $isService = true;
    /**
     * @var LogTarget
     */
    public static $logTargetInstance = null;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->setViewPath('@yii/debug/views');
        if (Yii::$app instanceof Application) {
            $this->initPanels();
        }
    }

    /**
     * 继承原有逻辑, 增加一个异步写日志的LogTarget
     *
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if (!self::$logTargetInstance) {
            self::$logTargetInstance = new LogTarget($this);
            self::$logTargetInstance->module = null; // 不要引用$this
        }
        $logTarget = clone self::$logTargetInstance;
        $logTarget->module = $this;
        $logTarget->tag = \SeasLog::getRequestID(); // 在高并发情况下可能会重复
        $this->logTarget = Yii::$app->getLog()->targets['debug'] = $logTarget;

        $app->on(Application::EVENT_BEFORE_REQUEST, function () use ($app) {
            $app->getView()->on(View::EVENT_END_BODY, [$this, 'renderToolbar']);
        });

        // TODO urlManager组件优化
        $app->getUrlManager()->addRules([
            [
                'class' => 'yii\web\UrlRule',
                'route' => $this->id,
                'pattern' => $this->id,
            ],
            [
                'class' => 'yii\web\UrlRule',
                'route' => $this->id . '/<controller>/<action>',
                'pattern' => $this->id . '/<controller:[\w\-]+>/<action:[\w\-]+>',
            ]
        ], false);
    }

    protected function initPanels()
    {
        // merge custom panels and core panels so that they are ordered mainly by custom panels
        if (empty($this->panels)) {
            $this->panels = $this->corePanels();
        } else {
            $corePanels = $this->corePanels();
            foreach ($corePanels as $id => $config) {
                if (isset($this->panels[$id])) {
                    unset($corePanels[$id]);
                }
            }
            $this->panels = array_filter(array_merge($corePanels, $this->panels));
        }

        foreach ($this->panels as $id => $config) {
            if (is_string($config)) {
                $config = ['class' => $config];
            }
            if (is_array($config)) {
                $config['module'] = $this;
                $config['id'] = $id;
                $this->panels[$id] = Yii::createObject($config);
            }

            if ($this->panels[$id] instanceof Panel && !$this->panels[$id]->isEnabled()) {
                unset($this->panels[$id]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function refresh($app = null)
    {
        $this->bootstrap(Yii::$app);
    }

    protected function corePanels()
    {
        return [
            'config' => ['class' => ConfigPanel::class],
            'request' => ['class' => RequestPanel::class],
            'log' => ['class' => 'yii\debug\panels\LogPanel'],
            'profiling' => ['class' => 'yii\debug\panels\ProfilingPanel'],
            'db' => ['class' => 'yii\debug\panels\DbPanel'],
            'assets' => ['class' => 'yii\debug\panels\AssetPanel'],
            'mail' => ['class' => 'yii\debug\panels\MailPanel'],
            'timeline' => ['class' => 'yii\debug\panels\TimelinePanel'],
            'user' => ['class' => 'yii\debug\panels\UserPanel'],
            'router' => ['class' => 'yii\debug\panels\RouterPanel']
        ];
    }
}

<?php

namespace yii\swoole;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\swoole\base\BootInterface;
use yii\swoole\base\Context;
use yii\swoole\base\EndInterface;
use yii\swoole\base\SLTrait;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\ArrayHelper;
use yii\swoole\process\BaseProcess;
use yii\swoole\server\ProcessServer;
use yii\swoole\server\Server;
use yii\swoole\web\Request;
use yii\swoole\web\Response;
use yii\swoole\web\Session;
use yii\web\NotFoundHttpException;
use yii\web\ResponseFormatterInterface;
use yii\web\UrlNormalizerRedirectException;

class Application extends Module implements ICoroutine
{
    use SLTrait;

    /**
     * @var string
     */
    public $defaultRoute = 'site';

    public $catchAll;

    public $controller;
    /**
     * @var static 当前进行中的$app实例, 存放的是一个通用的, 可以供复制的app实例
     */
    public static $workerApp = null;

    /**
     * @var array
     */
    public $process = [];

    /**
     * @var array
     */
    public $clean = [];

    /**
     * @var array
     */
    public $processPool = [];

    /**
     * @var array
     */
    public $beforeStart = [];

    /**
     * @var array
     */
    public $workerStart = [];

    /**
     * @event Event an event raised before the application starts to handle a request.
     */
    const EVENT_BEFORE_REQUEST = 'beforeRequest';
    /**
     * @event Event an event raised after the application successfully handles a request (before the response is sent out).
     */
    const EVENT_AFTER_REQUEST = 'afterRequest';
    /**
     * Application state used by [[state]]: application just started.
     */
    const STATE_BEGIN = 0;
    /**
     * Application state used by [[state]]: application is initializing.
     */
    const STATE_INIT = 1;
    /**
     * Application state used by [[state]]: application is triggering [[EVENT_BEFORE_REQUEST]].
     */
    const STATE_BEFORE_REQUEST = 2;
    /**
     * Application state used by [[state]]: application is handling the request.
     */
    const STATE_HANDLING_REQUEST = 3;
    /**
     * Application state used by [[state]]: application is triggering [[EVENT_AFTER_REQUEST]]..
     */
    const STATE_AFTER_REQUEST = 4;
    /**
     * Application state used by [[state]]: application is about to send response.
     */
    const STATE_SENDING_RESPONSE = 5;
    /**
     * Application state used by [[state]]: application has ended.
     */
    const STATE_END = 6;

    public $controllerNamespace = 'app\\controllers';

    public $name = 'My Application';

    public $charset = 'UTF-8';

    private $_language = 'en-US';

    public $sourceLanguage = 'en-US';

    public $layout = 'main';

    private $_requestedRoute;

    private $_requestedAction;

    private $_requestedParams;

    public $extensions;

    public $bootstrap = [];

    private $_state;

    public $loadedModules = [];

    public function getRequestedRoute()
    {
        $this->_requestedRoute = Context::get('_requestedRoute');
        return $this->_requestedRoute;
    }

    public function setRequestedRoute($value)
    {
        Context::set('_requestedRoute', $value);
    }

    public function getRequestedAction()
    {
        $this->_requestedAction = Context::get('_requestedAction');
        return $this->_requestedAction;
    }

    public function setRequestedAction($value)
    {
        Context::set('_requestedAction', $value);
    }

    public function getRequestedParams()
    {
        $this->_requestedParams = Context::get('_requestedParams');
        return $this->_requestedParams;
    }

    public function setRequestedParams($value)
    {
        Context::set('_requestedParams', $value);
    }

    public function getState()
    {
        $this->_state = Context::get('_state');
        return $this->_state;
    }

    public function setState($value)
    {
        Context::set('_state', $value);
    }

    public function getLanguage()
    {
        $this->_language = Context::get('_language');
        return $this->_language ?? 'en-US';
    }

    public function setLanguage($value)
    {
        Context::set('_language', $value);
    }

    public function release()
    {
        //自定义清理
        foreach ($this->clean as $name => $clean) {
            if ($clean instanceof EndInterface) {
                $clean->clean();
            }
        }
        Context::release();
    }

    public function __construct($config = [])
    {
        Yii::$app = $this;
        static::setInstance($this);

        $this->state = self::STATE_BEGIN;

        $this->preInit($config);

        $this->registerErrorHandler($config);

        $components = ArrayHelper::getValueByArray($config['components'], ['request', 'response', 'user', 'session', 'db']);

        Context::setComponents($components);

        unset($config['components']['request'], $config['components']['response'], $config['components']['user'], $config['components']['session'], $config['components']['db']);

        Component::__construct($config);
    }

    public function initProcess()
    {
        if (is_array($this->process)) {
            foreach ($this->process as $name => $obj) {
                if (isset($obj['boot']) && $obj['boot'] === true) {
                    $obj['name'] = $name;
                    $process = Yii::createObject($obj);
                    if ($process instanceof BaseProcess) {
                        $this->process[$name] = $process;
                        ProcessServer::getInstance()->start($process);
                    }
                }
            }
        }
    }

    private function initClean()
    {
        //自定义清理
        if (is_array($this->clean)) {
            foreach ($this->clean as $name => $obj) {
                if (!$obj instanceof EndInterface) {
                    $clean = Yii::createObject($obj);
                    $this->clean[$name] = $clean;
                }
            }
        }
    }

    private function initBoot(Server $server)
    {
        if (is_array($this->workerStart)) {
            foreach ($this->workerStart as $handle) {
                if (!$handle instanceof BootInterface) {
                    $handle = Yii::createObject($handle);
                }
                $handle->handle($server);
            }
        }
    }

    /**
     * @var string
     */
    protected $_rootPath;

    /**
     * @return string
     */
    public function getRootPath()
    {
        return $this->_rootPath;
    }

    /**
     * @param string $rootPath
     */
    public function setRootPath($rootPath)
    {
        $this->_rootPath = $rootPath;
    }

    /**
     * @var array
     */
    public $bootstrapRefresh = [];

    public function preInit(&$config)
    {
        if (!isset($config['id'])) {
            throw new InvalidConfigException('The "id" configuration for the Application is required.');
        }
        if (isset($config['basePath'])) {
            $this->setBasePath($config['basePath']);
            unset($config['basePath']);
        } else {
            throw new InvalidConfigException('The "basePath" configuration for the Application is required.');
        }

        if (isset($config['vendorPath'])) {
            $this->setVendorPath($config['vendorPath']);
            unset($config['vendorPath']);
        } else {
            // set "@vendor"
            $this->getVendorPath();
        }
        if (isset($config['runtimePath'])) {
            $this->setRuntimePath($config['runtimePath']);
            unset($config['runtimePath']);
        } else {
            // set "@runtime"
            $this->getRuntimePath();
        }

        if (isset($config['timeZone'])) {
            $this->setTimeZone($config['timeZone']);
            unset($config['timeZone']);
        } elseif (!ini_get('date.timezone')) {
            $this->setTimeZone('UTC');
        }

        if (isset($config['container'])) {
            $this->setContainer($config['container']);

            unset($config['container']);
        }

        // merge core components with custom components
        foreach ($this->coreComponents() as $id => $component) {
            if (!isset($config['components'][$id])) {
                $config['components'][$id] = $component;
            } elseif (is_array($config['components'][$id]) && !isset($config['components'][$id]['class'])) {
                $config['components'][$id]['class'] = $component['class'];
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->state = self::STATE_INIT;
        $this->bootstrap();
    }

    public function getUniqueId()
    {
        return '';
    }

    public function setBasePath($path)
    {
        parent::setBasePath($path);
        Yii::setAlias('@app', $this->getBasePath());
    }

    protected function registerErrorHandler(&$config)
    {
        if (YII_ENABLE_ERROR_HANDLER) {
            if (!isset($config['components']['errorHandler']['class'])) {
                echo "Error: no errorHandler component is configured.\n";
            }
            $this->set('errorHandler', $config['components']['errorHandler']);
            unset($config['components']['errorHandler']);
            $this->getErrorHandler()->register();
        }
    }

    /**
     * @var array 扩展缓存
     */
    public static $defaultExtensionCache = null;

    /**
     * 获取默认的扩展
     *
     * @return array|mixed
     */
    public function getDefaultExtensions()
    {
        if (static::$defaultExtensionCache === null) {
            $file = Yii::getAlias('@vendor/yiisoft/extensions.php');
            static::$defaultExtensionCache = is_file($file) ? include($file) : [];
        }
        return static::$defaultExtensionCache;
    }

    /**
     * @var bool
     */
    public static $webAliasInit = false;

    /**
     * 初始化流程
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function bootstrap()
    {
        if (!static::$webAliasInit) {
            $request = $this->getRequest();
            Yii::setAlias('@webroot', dirname($request->getScriptFile()));
            Yii::setAlias('@web', $request->getBaseUrl());
            static::$webAliasInit = true;
        }

        $this->extensionBootstrap();
        $this->moduleBootstrap();
        //加入启动引导
        $mods = $this->getModules();
        $this->createModules($mods, $this);
    }

    /**
     * 自动加载扩展的初始化
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function extensionBootstrap()
    {
        if (!$this->extensions) {
            $this->extensions = $this->getDefaultExtensions();
        }
        foreach ($this->extensions as $k => $extension) {
            if (!empty($extension['alias'])) {
                foreach ($extension['alias'] as $name => $path) {
                    Yii::setAlias($name, $path);
                }
            }
            if (isset($extension['bootstrap'])) {
                $this->bootstrap[] = $extension['bootstrap'];
                Yii::trace('Push extension bootstrap to module bootstrap list', __METHOD__);
            }
        }
    }

    /**
     * 自动加载模块的初始化
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function moduleBootstrap()
    {
        foreach ($this->bootstrap as $k => $class) {
            $component = null;
            if (is_string($class)) {
                if ($this->has($class)) {
                    $component = $this->get($class);
                } elseif ($this->hasModule($class)) {
                    $component = $this->getModule($class);
                } elseif (strpos($class, '\\') === false) {
                    throw new InvalidConfigException("Unknown bootstrapping component ID: $class");
                }
            }
            if (!isset($component)) {
                $component = Yii::createObject($class);
            }

            if ($component instanceof BootstrapInterface) {
                Yii::trace('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                $this->bootstrap[$k] = $component;
                $component->bootstrap($this);
                $this->bootstrap[$k] = $component;
            } else {
                Yii::trace('Bootstrap with ' . get_class($component), __METHOD__);
            }
        }
    }

    /**
     * 创建Module
     * @param type $modules
     */
    private function createModules($moduleConfig, $module)
    {
        foreach ($moduleConfig as $id => $_config) {
            if (is_array($_config)) {
                $mod = $module->getModule($id);
                if (isset($_config['modules'])) {
                    $this->createModules($_config['modules'], $mod);
                }
            }
        }
    }

    private $_homeUrl;

    /**
     * @return string the homepage URL
     */
    public function getHomeUrl()
    {
        if ($this->_homeUrl === null) {
            if ($this->getUrlManager()->showScriptName) {
                return $this->getRequest()->getScriptUrl();
            } else {
                return $this->getRequest()->getBaseUrl() . '/';
            }
        } else {
            return $this->_homeUrl;
        }
    }

    /**
     * @param string $value the homepage URL
     */
    public function setHomeUrl($value)
    {
        $this->_homeUrl = $value;
    }

    public function getTimeZone()
    {
        return date_default_timezone_get();
    }

    public function setTimeZone($value)
    {
        date_default_timezone_set($value);
    }

    public function getDb()
    {
        return Context::get('db');
    }

    public function getLog()
    {
        return $this->get('log');
    }

    public function getCache()
    {
        return $this->get('cache', false);
    }

    public function getFormatter()
    {
        return $this->get('formatter');
    }

    public function getView()
    {
        return $this->get('view');
    }

    public function getUrlManager()
    {
        return $this->get('urlManager');
    }

    public function getI18n()
    {
        return $this->get('i18n');
    }

    public function getMailer()
    {
        return $this->get('mailer');
    }

    public function getAuthManager()
    {
        return $this->get('authManager', false);
    }

    public function getAssetManager()
    {
        return $this->get('assetManager');
    }

    public function getSecurity()
    {
        return $this->get('security');
    }

    /**
     * Returns the error handler component.
     * @return ErrorHandler the error handler application component.
     */
    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }

    /**
     * Returns the request component.
     * @return Request the request component.
     */
    public function getRequest()
    {
        return Context::get('request');
    }

    /**
     * Returns the response component.
     * @return Response the response component.
     */
    public function getResponse()
    {
        return Context::get('response');
    }

    /**
     * Returns the session component.
     * @return Session the session component.
     */
    public function getSession()
    {
        return Context::get('session');
    }

    /**
     * Returns the user component.
     * @return User the user component.
     */
    public function getUser()
    {
        return Context::get('user');
    }

    /**
     * @inheritdoc
     */
    public function coreComponents()
    {
        return array_merge([
            'log' => ['class' => 'yii\swoole\log\Dispatcher'],
            'formatter' => ['class' => 'yii\i18n\Formatter'],
            'i18n' => ['class' => 'yii\i18n\I18N'],
            'mailer' => ['class' => 'yii\swoole\mailer\SwiftMailer'],
            'urlManager' => ['class' => 'yii\web\UrlManager'],
            'assetManager' => ['class' => 'yii\swoole\web\AssetManager'],
            'security' => ['class' => 'yii\base\Security'],
        ], [
            'request' => ['class' => 'yii\swoole\web\Request'],
            'response' => ['class' => 'yii\swoole\web\Response'],
            'user' => ['class' => 'yii\swoole\web\User'],
            'errorHandler' => ['class' => 'yii\swoole\web\ErrorHandler'],
        ]);
    }

    /**
     * 预热一些可以浅复制的对象
     */
    public function prepare(Server $server)
    {
        //Create Boot
        $this->initBoot($server);
        //Create Clean
        $this->initClean();

        foreach ($this->getResponse()->formatters as $type => $class) {
            if (!$class instanceof ResponseFormatterInterface) {
                $this->getResponse()->formatters[$type] = Yii::createObject($class);
            }
        }
    }

    /**
     * run之前先准备上下文信息
     */
    public function beforeRun()
    {
        $this->refresh();
    }

    public function refresh()
    {
        foreach ($this->bootstrap as $k => $component) {
            if (!is_object($component)) {
                if ($this->has($component)) {
                    $component = $this->get($component);
                } elseif ($this->hasModule($component)) {
                    $component = $this->getModule($component);
                }
            }
            if (in_array(get_class($component), $this->bootstrapRefresh)) {
                /** @var BootstrapInterface $component */
                $component->bootstrap($this);
            } elseif ($component instanceof Refreshable) {
                $component->refresh();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        try {

            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);

            $this->state = self::STATE_HANDLING_REQUEST;
            $response = $this->handleRequest($this->getRequest());

            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);

            $this->state = self::STATE_SENDING_RESPONSE;

            $response->send();

            $this->state = self::STATE_END;

            return $response->exitStatus;

        } catch (ExitException $e) {
            $this->end($e->statusCode, isset($response) ? $response : null);
            return $e->statusCode;

        }
    }

    public function handleRequest($request)
    {
        if (empty($this->catchAll)) {
            try {
                list ($route, $params) = $request->resolve();
            } catch (UrlNormalizerRedirectException $e) {
                $url = $e->url;
                if (is_array($url)) {
                    if (isset($url[0])) {
                        // ensure the route is absolute
                        $url[0] = '/' . ltrim($url[0], '/');
                    }
                    $url += $request->getQueryParams();
                }
                return $this->getResponse()->redirect(Url::to($url, $e->scheme), $e->statusCode);
            }
        } else {
            $route = $this->catchAll[0];
            $params = $this->catchAll;
            unset($params[0]);
        }
        try {
            Yii::trace("Route requested: '$route'", __METHOD__);
            $this->setRequestedRoute($route);
            $result = $this->runAction($route, $params);
            if ($result instanceof Response) {
                return $result;
            } else {
                $response = $this->getResponse();
                if ($result !== null) {
                    $response->data = $result;
                }

                return $response;
            }
        } catch (InvalidRouteException $e) {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'), $e->getCode(), $e);
        }
    }

    /**
     * 阻止默认的exit执行
     *
     * @param int $status
     * @param mixed $response
     * @return int|void
     */
    public function end($status = 0, $response = null)
    {
        if (!Application::$workerApp) {
            return parent::run();
        }
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
        }

        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $response = $response ?: $this->getResponse();
            $response->send();
        }
        return 0;
    }

    private $_runtimePath;

    /**
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files.
     * Defaults to the "runtime" subdirectory under [[basePath]].
     */
    public function getRuntimePath()
    {
        if ($this->_runtimePath === null) {
            $this->setRuntimePath($this->getBasePath() . DIRECTORY_SEPARATOR . 'runtime');
        }

        return $this->_runtimePath;
    }

    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     */
    public function setRuntimePath($path)
    {
        $this->_runtimePath = Yii::getAlias($path);
        Yii::setAlias('@runtime', $this->_runtimePath);
    }

    private $_vendorPath;

    /**
     * Returns the directory that stores vendor files.
     * @return string the directory that stores vendor files.
     * Defaults to "vendor" directory under [[basePath]].
     */
    public function getVendorPath()
    {
        if ($this->_vendorPath === null) {
            $this->setVendorPath($this->getBasePath() . DIRECTORY_SEPARATOR . 'vendor');
        }

        return $this->_vendorPath;
    }

    /**
     * Sets the directory that stores vendor files.
     * @param string $path the directory that stores vendor files.
     */
    public function setVendorPath($path)
    {
        $this->_vendorPath = Yii::getAlias($path);
        Yii::setAlias('@vendor', $this->_vendorPath);
        Yii::setAlias('@bower', $this->_vendorPath . DIRECTORY_SEPARATOR . 'bower');
        Yii::setAlias('@npm', $this->_vendorPath . DIRECTORY_SEPARATOR . 'npm');
    }

    public function setContainer($config)
    {
        Yii::configure(Yii::$container, $config);
    }

}

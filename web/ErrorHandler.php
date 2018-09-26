<?php

namespace yii\swoole\web;

use Yii;
use yii\base\ErrorException;
use yii\base\UserException;
use yii\swoole\Application;
use yii\swoole\base\Output;
use yii\swoole\helpers\CoroHelper;
use yii\web\HttpException;

class ErrorHandler extends \yii\web\ErrorHandler
{
    private $_memoryReserve;

    /**
     * Register this error handler.
     */
    public function register()
    {
        ini_set('display_errors', false);
        set_exception_handler([$this, 'handleException']);
        if (defined('HHVM_VERSION')) {
            set_error_handler([$this, 'handleHhvmError']);
        } else {
            set_error_handler([$this, 'handleError']);
        }
        if ($this->memoryReserveSize > 0) {
            $this->_memoryReserve = str_repeat('x', $this->memoryReserveSize);
        }
        register_shutdown_function([$this, 'handleFatalError']);
    }

    /**
     * Unregisters this error handler by restoring the PHP error and exception handlers.
     */
    public function unregister()
    {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @inheritdoc
     */
    protected function renderException($exception)
    {
        if (Yii::$app->has('response')) {
            $response = Yii::$app->getResponse();
            // reset parameters of response to avoid interference with partially created response data
            // in case the error occurred while sending the response.
            $response->isSent = false;
            $response->stream = null;
            $response->data = null;
            $response->content = null;

        } else {
            if (!Application::$workerApp) {
                $response = new \yii\web\Response();
            } else {
                $response = new Response();
            }
        }

        $useErrorView = $response->format === Response::FORMAT_HTML && (!YII_DEBUG || $exception instanceof UserException);
        if (isset(Yii::$app->params['ErrorFormat'])) {
            $response->format = Yii::$app->params['ErrorFormat'];
        }
        if ($useErrorView && $this->errorAction !== null) {
            $result = Yii::$app->runAction($this->errorAction);
            if ($result instanceof Response) {
                $response = $result;
            } else {
                $response->data = $result;
            }
        } elseif ($response->format === Response::FORMAT_HTML) {
            if (YII_ENV_TEST || isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                // AJAX request
                $response->data = '<pre>' . $this->htmlEncode(static::convertExceptionToString($exception)) . '</pre>';
            } else {
                // if there is an error during error rendering it's useful to
                // display PHP error in debug mode instead of a blank screen
                if (YII_DEBUG) {
                    ini_set('display_errors', 1);
                }
                $file = $useErrorView ? $this->errorView : $this->exceptionView;
                $response->data = $this->renderFile($file, [
                    'exception' => $exception,
                ]);
            }
        } elseif ($response->format === Response::FORMAT_RAW) {
            $response->data = static::convertExceptionToString($exception);
        } else {
            $response->data = $this->convertExceptionToArray($exception);
        }

        if ($exception instanceof HttpException) {
            $response->setStatusCode($exception->statusCode);
        } else {
            $response->setStatusCode(500);
        }

        $response->send();

        $this->exception = null;
    }

    /**
     * @inheritdoc
     */
    public function handleException($exception)
    {
        if (!Application::$workerApp) {
            parent::handleException($exception);
            return;
        }

        $this->exception = $exception;

        $this->flush($exception);

        $this->exception = null;
    }

    public function handleError($code, $message, $file, $line)
    {
        if (error_reporting() & $code) {
            // load ErrorException manually here because autoloading them will not work
            // when error occurs while autoloading a class
            if (!class_exists('yii\\base\\ErrorException', false)) {
                require_once Yii::getAlias('@vendor/yiisoft/yii2/base/ErrorException.php');
            }
            $exception = new ErrorException($message, $code, $code, $file, $line);

            // in case error appeared in __toString method we can't throw any exception
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);
            foreach ($trace as $frame) {
                if ($frame['function'] === '__toString') {
                    $this->handleException($exception);
                    if (defined('HHVM_VERSION')) {
                        flush();
                    }
                }
            }

            throw $exception;
        }

        return false;
    }

    public function handleFatalError()
    {
        unset($this->_memoryReserve);

        // load ErrorException manually here because autoloading them will not work
        // when error occurs while autoloading a class
        if (!class_exists('yii\\base\\ErrorException', false)) {
            require_once Yii::getAlias('@vendor/yiisoft/yii2/base/ErrorException.php');
        }

        $error = error_get_last();

        if (ErrorException::isFatalError($error)) {
            $exception = new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
            $this->exception = $exception;

            $this->flush($exception);
        }
    }

    private function flush($exception)
    {
        $id = CoroHelper::getId();
        $this->logException($exception);
        if (isset(Yii::$server->currentSwooleResponse[$id])) {
            Yii::$server->currentSwooleResponse[$id]->status(500);
            try {
                if ($this->discardExistingOutput) {
                    $this->clearOutput();
                }
                $this->renderException($exception);
            } catch (\Exception $e) {
                // an other exception could be thrown while displaying the exception
                $msg = "An Error occurred while handling another error:\n";
                $msg .= (string)$e;
                $msg .= "\nPrevious exception:\n";
                $msg .= (string)$exception;
                if (YII_DEBUG) {
                    $html = '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
                } else {
                    $html = 'An internal server error occurred.';
                }

                Yii::$server->currentSwooleResponse[$id]->header('Content-Type', 'text/html; charset=utf-8');
                Yii::$server->currentSwooleResponse[$id]->end($html);
            }
        }
    }

    public function converter($exception, $method)
    {
        $array = $this->{$method}($exception);
        $array = json_decode(json_encode($array));
        return $array;
    }

}

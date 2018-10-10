<?php

namespace yii\swoole\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\swoole\web\formatter\JsonResponseFormatter;

class Response extends \yii\web\Response
{
    private $_statusCode = [];

    private $_headers;

    private $_cookies;

    /**
     * swoole响应请求
     *
     * @var \Swoole\Http\Response
     */
    protected $swooleResponse;

    /**
     * @return \Swoole\Http\Response
     */
    public function getSwooleResponse(): ?\Swoole\Http\Response
    {
        return $this->swooleResponse;
    }

    /**
     * @param \Swoole\Http\Response $swooleResponse
     * @return $this
     */
    public function setSwooleResponse(\Swoole\Http\Response $swooleResponse)
    {
        $this->swooleResponse = $swooleResponse;
        return $this;
    }

    public function init()
    {
        if ($this->version === null) {
            $request = Yii::$app->getRequest()->getSwooleRequest();
            if ($request && isset($request->server['server_protocol']) && $request->server['server_protocol'] === 'HTTP/1.0') {
                $this->version = '1.0';
            } else {
                $this->version = '1.1';
            }
        }
        if ($this->charset === null) {
            $this->charset = \Yii::$app->charset;
        }
        $this->formatters = array_merge($this->defaultFormatters(), $this->formatters);
    }

    protected function getHttpRange($fileSize)
    {
        $request = Yii::$app->getRequest()->getSwooleRequest();
        if ($request && !isset($request->server['http_range']) || $request->server['http_range'] === '-') {
            return [0, $fileSize - 1];
        }
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $request->server['http_range'], $matches)) {
            return false;
        }
        if ($matches[1] === '') {
            $start = $fileSize - $matches[2];
            $end = $fileSize - 1;
        } elseif ($matches[2] !== '') {
            $start = $matches[1];
            $end = $matches[2];
            if ($end >= $fileSize) {
                $end = $fileSize - 1;
            }
        } else {
            $start = $matches[1];
            $end = $fileSize - 1;
        }
        if ($start < 0 || $start > $end) {
            return false;
        } else {
            return [$start, $end];
        }
    }

    protected function sendHeaders()
    {
        if ($this->isSent) {
            return;
        }
        $statusCode = $this->getStatusCode();
        $this->swooleResponse->status($statusCode);
        if ($this->_headers) {
            foreach ($this->getHeaders() as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                // set replace for first occurrence of header but false afterwards to allow multiple
                foreach ($values as $value) {
                    $this->swooleResponse->header($name, $value);
                }
            }
        }
        $this->sendCookies();
    }

    protected function sendCookies()
    {
        if ($this->_cookies === null) {
            return;
        }
        $request = Yii::$app->getRequest();
        if ($request->enableCookieValidation) {
            if ($request->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($request) . '::cookieValidationKey must be configured with a secret key.');
            }
            $validationKey = $request->cookieValidationKey;
        }
        foreach ($this->getCookies() as $cookie) {
            $value = $cookie->value;
            if ($cookie->expire != 1 && isset($validationKey)) {
                $value = Yii::$app->getSecurity()->hashData(serialize([$cookie->name, $value]), $validationKey);
            }
            $this->swooleResponse->cookie($cookie->name, $value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly);
        }
    }

    protected function sendContent()
    {
        if ($this->stream === null) {
            $this->swooleResponse->end($this->content);

            return;
        }

        set_time_limit(0); // Reset time limit for big files
        $chunkSize = 8 * 1024 * 1024; // 8MB per chunk

        if (is_array($this->stream)) {
            list($handle, $begin, $end) = $this->stream;
            fseek($handle, $begin);
            while (!feof($handle) && ($pos = ftell($handle)) <= $end) {
                if ($pos + $chunkSize > $end) {
                    $chunkSize = $end - $pos + 1;
                }
                $this->swooleResponse->write(\Swoole\Coroutine::fread($handle, $chunkSize));
                flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
            }
            fclose($handle);
            $this->swooleResponse->end();
        } else {
            while (!feof($this->stream)) {
                $this->swooleResponse->write(\Swoole\Coroutine::fread($this->stream, $chunkSize));
                flush();
            }
            fclose($this->stream);
            $this->swooleResponse->end();
        }
    }

    public function sendFile($filePath, $attachmentName = null, $options = [])
    {
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = yii\helpers\FileHelper::getMimeTypeByExtension($filePath);
        }
        if ($attachmentName === null) {
            $attachmentName = basename($filePath);
        }
        $this->swooleResponse->header('Content-disposition', 'attachment; filename="' . urlencode($attachmentName) . '.xlsx"');
        $this->swooleResponse->header('Content-Type', $options['mimeType']);
        $this->swooleResponse->header('Content-Transfer-Encoding', 'binary');
        $this->swooleResponse->header('Cache-Control', 'must-revalidate');
        $this->swooleResponse->header('Pragma', 'public');
        $this->swooleResponse->sendfile($filePath);
    }

    public function xSendFile($filePath, $attachmentName = null, $options = [])
    {
        $this->sendFile($filePath, $attachmentName, $options);
    }
}

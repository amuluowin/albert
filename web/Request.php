<?php

namespace yii\swoole\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\swoole\helpers\ArrayHelper;
use yii\web\Cookie;
use yii\web\CookieCollection;
use yii\web\HeaderCollection;
use yii\web\RequestParserInterface;

class Request extends \yii\web\Request
{
    /**
     * @var array the headers in this collection (indexed by the header names)
     */
    private $_headers;

    /**
     * @var \Swoole\Http\Request
     */
    private $swooleRequest;

    /**
     * @return \Swoole\Http\Request
     */
    public function getSwooleRequest(): ?\Swoole\Http\Request
    {
        return $this->swooleRequest;
    }

    /**
     * @param \Swoole\Http\Request $swooleRequest
     * @return $this
     */
    public function setSwooleRequest(\Swoole\Http\Request $swooleRequest)
    {
        $this->swooleRequest = $swooleRequest;
        return $this;
    }

    /**
     * Returns the header collection.
     * The header collection contains incoming HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection();
            $headers = ArrayHelper::getValue($this->swooleRequest, 'header', []);
            foreach ($headers as $name => $value) {
                $this->_headers->add($name, $value);
            }
            $this->filterHeaders($this->_headers);
        }
        return $this->_headers;
    }

    /**
     * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
     * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
     * The value returned is turned into upper case.
     */
    public function getMethod()
    {
        if (isset($this->swooleRequest->post[$this->methodParam])) {
            return strtoupper($this->swooleRequest->post[$this->methodParam]);
        }
        if ($this->headers->has('X-Http-Method-Override')) {
            return strtoupper($this->headers->get('X-Http-Method-Override'));
        }

        if (isset($this->swooleRequest->server['request_method'])) {
            return strtoupper($this->swooleRequest->server['request_method']);
        }
    }

    protected function resolveRequestUri()
    {
        if (isset($this->swooleRequest->server['request_uri'])) {
            $requestUri = $this->swooleRequest->server['request_uri'];
        } elseif ($this->headers->has('X-Rewrite-Url')) { // IIS
            $requestUri = $this->headers->get('X-Rewrite-Url');
        } else {
            throw new InvalidConfigException('Unable to determine the request URI.');
        }

        return $requestUri;
    }

    public function getQueryString()
    {
        return isset($this->swooleRequest->server['query_string']) ? $this->swooleRequest->server['query_string'] : '';
    }

    public function getIsSecureConnection()
    {
        if (isset($this->swooleRequest->server['https']) && (strcasecmp($this->swooleRequest->server['https'], 'on') === 0 || $this->swooleRequest->server['https'] == 1)) {
            return true;
        }
        foreach ($this->secureProtocolHeaders as $header => $values) {
            if (($headerValue = $this->headers->get($header, null)) !== null) {
                foreach ($values as $value) {
                    if (strcasecmp($headerValue, $value) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function getServerName()
    {
        return isset($this->swooleRequest->server['server_name']) ? $this->swooleRequest->server['server_name'] : null;
    }

    public function getServerPort()
    {
        return isset($this->swooleRequest->server['server_port']) ? $this->swooleRequest->server['server_port'] : null;
    }

    public function getRemoteIP()
    {
        return isset($this->swooleRequest->server['remote_addr']) ? $this->swooleRequest->server['remote_addr'] : null;
    }

    public function getContentType()
    {
        if (isset($this->swooleRequest->header['content-type'])) {
            return $this->swooleRequest->header['content-type'];
        }

        //fix bug https://bugs.php.net/bug.php?id=66606
        return $this->headers->get('Content-Type');
    }

    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($this->swooleRequest->cookie as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $data = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }
                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = new Cookie([
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            foreach ($this->swooleRequest->cookie as $name => $value) {
                $cookies[$name] = new Cookie([
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    private $_pathInfo;

    /**
     * Returns the path info of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting and ending slashes are both removed.
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is already URL-decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    public function getPathInfo()
    {
        if ($this->_pathInfo === null) {
            $this->_pathInfo = $this->swooleRequest->server['path_info'];
        }
        return $this->_pathInfo;
    }

    /**
     * Sets the path info of the current request.
     * This method is mainly provided for testing purpose.
     * @param string $value the path info of the current request
     */
    public function setPathInfo($value)
    {
        $this->_pathInfo = ltrim($value, '/');
    }

    private $_rawBody;

    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = $this->swooleRequest->rawContent();
        }

        return $this->_rawBody;
    }

    /**
     * Sets the raw HTTP request body, this method is mainly used by test scripts to simulate raw HTTP requests.
     * @param string $rawBody the request body
     */
    public function setRawBody($rawBody)
    {
        $this->_rawBody = $rawBody;
    }

    private $_bodyParams;

    /**
     * Returns the request parameters given in the request body.
     *
     * Request parameters are determined using the parsers configured in [[parsers]] property.
     * If no parsers are configured for the current [[contentType]] it uses the PHP function `mb_parse_str()`
     * to parse the [[rawBody|request body]].
     * @return array the request parameters given in the request body.
     * @throws \yii\base\InvalidConfigException if a registered parser does not implement the [[RequestParserInterface]].
     * @see getMethod()
     * @see getBodyParam()
     * @see setBodyParams()
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            if (isset($this->swooleRequest->post[$this->methodParam])) {
                $this->_bodyParams = $this->swooleRequest->post;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }

            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }

            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException('The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.');
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $rawContentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $_POST;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }

    /**
     * Sets the request body parameters.
     * @param array $values the request body parameters (name-value pairs)
     * @see getBodyParam()
     * @see getBodyParams()
     */
    public function setBodyParams($values)
    {
        $this->_bodyParams = $values;
    }

    private $_traceId;

    /**
     * Returns the traceId.
     * @return string|null traceId, null if not available
     */
    public function getTraceId()
    {
        if (!isset($this->_traceId)) {
            $this->_traceId = Yii::$app->snowflake->nextId();
        }
        return $this->_traceId;
    }

    public function setTraceId($tid)
    {
        $this->_traceId = $tid;
    }
}

<?php

namespace yii\swoole\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;
use yii\swoole\coroutine\ICoroutine;
use yii\swoole\helpers\CoroHelper;
use yii\web\Cookie;
use yii\web\CookieCollection;
use yii\web\HeaderCollection;
use yii\web\RequestParserInterface;

/**
 * @property swoole_http_request swooleRequest
 */
class Request extends \yii\web\Request implements ICoroutine
{
    /**
     * @var CookieCollection Collection of request cookies.
     */
    private $_cookies = [];
    /**
     * @var array the headers in this collection (indexed by the header names)
     */
    private $_headers = [];
    private $_baseUrl = [];
    private $_scriptUrl = [];

    public function clear()
    {
        $id = CoroHelper::getId();
        unset($this->_baseUrl[$id]);
        unset($this->_scriptFile[$id]);
        unset($this->_headers[$id]);
        unset($this->_cookies[$id]);
        unset($this->_rawBody[$id]);
        unset($this->_bodyParams[$id]);
        unset($this->_pathInfo[$id]);
        unset($this->_queryParams[$id]);
        unset($this->_url[$id]);
        unset($this->_contentTypes[$id]);
        unset($this->_csrfToken[$id]);
        unset($this->_traceId[$id]);
    }

    /**
     * Returns the header collection.
     * The header collection contains incoming HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_headers[$id])) {
            $this->_headers[$id] = new HeaderCollection;
            $headers = Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->header;
            foreach ($headers as $name => $value) {
                $this->_headers[$id]->add($name, $value);
            }
            $this->filterHeaders($this->_headers[$id]);
        }
        return $this->_headers[$id];
    }

    public function setHeaders($headers)
    {
        $id = CoroHelper::getId();
        if (!isset($this->_headers[$id]) && is_array($headers)) {
            $this->_headers[$id] = new HeaderCollection();
            foreach ($headers as $name => $value) {
                $this->_headers[$id]->add($name, $value);
            }
            $this->filterHeaders($this->_headers[$id]);
        }
    }

    private $_post = [];

    /**
     * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
     * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
     * The value returned is turned into upper case.
     */
    public function getMethod()
    {
        $id = CoroHelper::getId();
        if (isset(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->post[$this->methodParam])) {
            return strtoupper(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->post[$this->methodParam]);
        }
        if ($this->headers->has('X-Http-Method-Override')) {
            return strtoupper($this->headers->get('X-Http-Method-Override'));
        }

        if (isset(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['request_method'])) {
            return strtoupper(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['request_method']);
        }
    }

    public function setMethod($_post)
    {
        $this->_post[CoroHelper::getId()] = $_post;
    }

    protected function resolveRequestUri()
    {
        $id = CoroHelper::getId();
        if (isset(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['request_uri'])) {
            $requestUri = Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['request_uri'];
        } elseif ($this->headers->has('X-Rewrite-Url')) { // IIS
            $requestUri = $this->headers->get('X-Rewrite-Url');
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            if ($requestUri !== '' && $requestUri[0] !== '/') {
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0 CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else {
            throw new InvalidConfigException('Unable to determine the request URI.');
        }

        return $requestUri;
    }

    public function getQueryString()
    {
        $id = CoroHelper::getId();
        return isset(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['query_string']) ? Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['query_string'] : '';
    }

    public function getIsSecureConnection()
    {
        $id = CoroHelper::getId();
        return strcasecmp(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['server_protocol'], 'https');
    }

    public function getServerName()
    {
        return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    public function getServerPort()
    {
        $id = CoroHelper::getId();
        return Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['server_port'];
    }

    public function getReferrer()
    {
        return $this->headers->get('Referer');
    }

    public function getUserAgent()
    {
        return $this->headers->get('User-Agent');
    }

    public function getUserIP()
    {
        foreach ($this->ipHeaders as $ipHeader) {
            if ($this->headers->has($ipHeader)) {
                return trim(explode(',', $this->headers->get($ipHeader))[0]);
            }
        }

        return $this->getRemoteIP();
    }

    public function getRemoteIP()
    {
        $id = CoroHelper::getId();
        return Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['remote_addr'];
    }

    public function getUserHost()
    {
        foreach ($this->ipHeaders as $ipHeader) {
            if ($this->headers->has($ipHeader)) {
                return gethostbyaddr(trim(explode(',', $this->headers->get($ipHeader))[0]));
            }
        }

        return $this->getRemoteHost();
    }

    public function getAuthUser()
    {
        return $this->getAuthCredentials()[0];
    }

    public function getAuthPassword()
    {
        return $this->getAuthCredentials()[1];
    }

    public function getContentType()
    {
        $id = CoroHelper::getId();
        if (isset(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->header['content-type'])) {
            return Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->header['content-type'];
        }

        //fix bug https://bugs.php.net/bug.php?id=66606
        return $this->headers->get('Content-Type');
    }

    public function getETags()
    {
        if ($this->headers->has('If-None-Match')) {
            return preg_split('/[\s,]+/', str_replace('-gzip', '', $this->headers->get('If-None-Match')), -1, PREG_SPLIT_NO_EMPTY);
        }

        return [];
    }

    protected function loadCookies()
    {
        $id = CoroHelper::getId();
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($_COOKIE[$id] as $name => $value) {
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
            foreach ($_COOKIE[$id] as $name => $value) {
                $cookies[$name] = new Cookie([
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    private $_rawBody = [];

    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function getRawBody()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_rawBody[$id])) {
            $this->_rawBody[$id] = Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->rawContent();
        }
        return $this->_rawBody[$id];
    }

    /**
     * Sets the raw HTTP request body, this method is mainly used by test scripts to simulate raw HTTP requests.
     * @param $rawBody
     */
    public function setRawBody($rawBody)
    {
        $this->_rawBody[CoroHelper::getId()] = $rawBody;
    }

    private $_bodyParams = [];

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
        $id = CoroHelper::getId();
        if (!isset($this->_bodyParams[$id])) {
            if (isset(Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->post[$this->methodParam])) {
                $this->_bodyParams[$id] = Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->post;
                unset($this->_bodyParams[$id][$this->methodParam]);
                return $this->_bodyParams[$id];
            }
            $contentType = $this->getContentType();
            if (($pos = strpos($contentType, ';')) !== false) {
                // e.g. application/json; charset=UTF-8
                $contentType = substr($contentType, 0, $pos);
            }
            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams[$id] = $parser->parse($this->getRawBody(), $contentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams[$id] = $parser->parse($this->getRawBody(), $contentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in         Yii::$app->getSwooleServer()->currentSwooleRequest[CoroHelper::getId()]->post
                $this->_bodyParams[$id] = $_POST[$id];
            } else {
                $this->_bodyParams[$id] = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams[$id]);
            }
        }
        return $this->_bodyParams[$id];
    }

    /**
     * Sets the request body parameters.
     * @param array $values the request body parameters (name-value pairs)
     * @see getBodyParam()
     * @see getBodyParams()
     */
    public function setBodyParams($values)
    {
        $this->_bodyParams[CoroHelper::getId()] = $values;
    }

    private $_queryParams = [];

    /**
     * Returns the request parameters given in the [[queryString]].
     *
     * This method will return the contents of `$_GET` if params where not explicitly set.
     * @return array the request GET parameter values.
     * @see setQueryParams()
     */
    public function getQueryParams()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_queryParams[$id])) {
            return $_GET[$id];
        }
        return $this->_queryParams[$id];
    }

    /**
     * Sets the request [[queryString]] parameters.
     * @param array $values the request query parameters (name-value pairs)
     * @see getQueryParam()
     * @see getQueryParams()
     */
    public function setQueryParams($values)
    {
        $this->_queryParams[CoroHelper::getId()] = $values;
    }

    public function getQueryParam($name, $defaultValue = null)
    {
        $params = $this->getQueryParams();

        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    private $_hostInfo = [];
    private $_hostName = [];

    /**
     * Returns the schema and host part of the current request URL.
     * The returned URL does not have an ending slash.
     * By default this is determined based on the user request information.
     * You may explicitly specify it by setting the [[setHostInfo()|hostInfo]] property.
     * @return string schema and hostname part (with port number if needed) of the request URL (e.g. `http://www.yiiframework.com`)
     * @see setHostInfo()
     */
    public function getHostInfo()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_hostInfo[$id])) {
            $secure = $this->getIsSecureConnection();
            $http = $secure ? 'https' : 'http';

            if ($this->headers->has('X-Forwarded-Host')) {
                $this->_hostInfo = $http . '://' . $this->headers->get('X-Forwarded-Host');
            } elseif ($this->headers->has('Host')) {
                $this->_hostInfo = $http . '://' . $this->headers->get('Host');
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                $this->_hostInfo = $http . '://' . $_SERVER['SERVER_NAME'];
                $port = $secure ? $this->getSecurePort() : $this->getPort();
                if (($port !== 80 && !$secure) || ($port !== 443 && $secure)) {
                    $this->_hostInfo .= ':' . $port;
                }
            }
        }
        return $this->_hostInfo[$id];
    }

    public function setHostInfo($value)
    {
        $id = CoroHelper::getId();
        $this->_hostName[$id] = null;
        $this->_hostInfo[$id] = $value === null ? null : rtrim($value, '/');
    }

    /**
     * Returns the host part of the current request URL.
     * Value is calculated from current [[getHostInfo()|hostInfo]] property.
     *
     * > Warning: The content of this value may not be reliable, dependent on the server
     * > configuration. Please refer to [[getHostInfo()]] for more information.
     *
     * @return string|null hostname part of the request URL (e.g. `www.yiiframework.com`)
     * @see getHostInfo()
     * @since 2.0.10
     */
    public function getHostName()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_hostName[$id])) {
            $this->_hostName[$id] = parse_url($this->getHostInfo(), PHP_URL_HOST);
        }

        return $this->_hostName[$id];
    }

    /**
     * Returns the relative URL for the application.
     * This is similar to [[scriptUrl]] except that it does not include the script file name,
     * and the ending slashes are removed.
     * @return string the relative URL for the application
     * @see setScriptUrl()
     */
    public function getBaseUrl()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_baseUrl[$id])) {
            $this->_baseUrl[$id] = rtrim(dirname($this->getScriptUrl()), '\\/');
        }
        return $this->_baseUrl[$id];
    }

    /**
     * Sets the relative URL for the application.
     * By default the URL is determined based on the entry script URL.
     * This setter is provided in case you want to change this behavior.
     * @param string $value the relative URL for the application
     */
    public function setBaseUrl($value)
    {
        $this->_baseUrl[CoroHelper::getId()] = $value;
    }

    /**
     * Returns the relative URL of the entry script.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string the relative URL of the entry script.
     * @throws InvalidConfigException if unable to determine the entry script URL
     */
    public function getScriptUrl()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_scriptUrl[$id])) {
            $scriptFile = $this->getScriptFile();
            $scriptName = basename($scriptFile);
            if (basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl[$id] = $_SERVER['SCRIPT_NAME'];
            } elseif (basename($_SERVER['PHP_SELF']) === $scriptName) {
                $this->_scriptUrl[$id] = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
                $this->_scriptUrl[$id] = $_SERVER['ORIG_SCRIPT_NAME'];
            } elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $scriptName)) !== false) {
                $this->_scriptUrl[$id] = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
            } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
                $this->_scriptUrl[$id] = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $scriptFile));
            } else {
                throw new InvalidConfigException('Unable to determine the entry script URL.');
            }
        }
        return $this->_scriptUrl[$id];
    }

    /**
     * Sets the relative URL for the application entry script.
     * This setter is provided in case the entry script URL cannot be determined
     * on certain Web servers.
     * @param string $value the relative URL for the application entry script.
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl[CoroHelper::getId()] = '/' . trim($value, '/');
    }

    private $_scriptFile;

    /**
     * Returns the entry script file path.
     * The default implementation will simply return `$_SERVER['SCRIPT_FILENAME']`.
     * @return string the entry script file path
     */
    public function getScriptFile()
    {
        $id = CoroHelper::getId();
        return isset($this->_scriptFile[$id]) ? $this->_scriptFile[$id] : $_SERVER['SCRIPT_FILENAME'];
    }

    /**
     * Sets the entry script file path.
     * The entry script file path normally can be obtained from `$_SERVER['SCRIPT_FILENAME']`.
     * If your server configuration does not return the correct value, you may configure
     * this property to make it right.
     * @param string $value the entry script file path.
     */
    public function setScriptFile($value)
    {
        $this->_scriptFile[CoroHelper::getId()] = $value;
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
        $id = CoroHelper::getId();
        if (!isset($this->_pathInfo[$id])) {
            $this->_pathInfo[$id] = Yii::$app->getSwooleServer()->currentSwooleRequest[$id]->server['path_info'];
        }
        return $this->_pathInfo[$id];
    }

    /**
     * Sets the path info of the current request.
     * This method is mainly provided for testing purpose.
     * @param string $value the path info of the current request
     */
    public function setPathInfo($value)
    {
        $this->_pathInfo[CoroHelper::getId()] = ltrim($value, '/');
    }

    private $_url;

    /**
     * Returns the currently requested relative URL.
     * This refers to the portion of the URL that is after the [[hostInfo]] part.
     * It includes the [[queryString]] part if any.
     * @return string the currently requested relative URL. Note that the URI returned is URL-encoded.
     * @throws InvalidConfigException if the URL cannot be determined due to unusual server configuration
     */
    public function getUrl()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_url[$id])) {
            $this->_url[$id] = $this->resolveRequestUri();
        }
        return $this->_url[$id];
    }

    /**
     * Sets the currently requested relative URL.
     * The URI must refer to the portion that is after [[hostInfo]].
     * Note that the URI should be URL-encoded.
     * @param string $value the request URI to be set
     */
    public function setUrl($value)
    {
        $this->_url[CoroHelper::getId()] = $value;
    }


    private $_port;

    /**
     * Returns the port to use for insecure requests.
     * Defaults to 80, or the port specified by the server if the current
     * request is insecure.
     * @return integer port number for insecure requests.
     * @see setPort()
     */
    public function getPort()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_port[$id])) {
            $serverPort = $this->getServerPort();
            $this->_port[$id] = !$this->getIsSecureConnection() && $serverPort !== null ? $serverPort : 80;
        }
        return $this->_port[$id];
    }

    /**
     * Sets the port to use for insecure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param integer $value port number.
     */
    public function setPort($value)
    {
        $id = CoroHelper::getId();
        if ($value != $this->_port[$id]) {
            $this->_port[$id] = (int)$value;
            $this->_hostInfo[$id] = null;
        }
    }

    private $_securePort;

    /**
     * Returns the port to use for secure requests.
     * Defaults to 443, or the port specified by the server if the current
     * request is secure.
     * @return integer port number for secure requests.
     * @see setSecurePort()
     */
    public function getSecurePort()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_securePort[$id])) {
            $serverPort = $this->getServerPort();
            $this->_securePort[$id] = $this->getIsSecureConnection() && $serverPort !== null ? $serverPort : 443;
        }
        return $this->_securePort[$id];
    }

    /**
     * Sets the port to use for secure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param integer $value port number.
     */
    public function setSecurePort($value)
    {
        $id = CoroHelper::getId();
        if ($value != $this->_securePort[$id]) {
            $this->_securePort[$id] = (int)$value;
            $this->_hostInfo[$id] = null;
        }
    }

    private $_contentTypes;

    /**
     * Returns the content types acceptable by the end user.
     * This is determined by the `Accept` HTTP header. For example,
     *
     * ```php
     * $_SERVER['HTTP_ACCEPT'] = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $types = $request->getAcceptableContentTypes();
     * print_r($types);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @return array the content types ordered by the quality score. Types with the highest scores
     * will be returned first. The array keys are the content types, while the array values
     * are the corresponding quality score and other parameters as given in the header.
     */
    public function getAcceptableContentTypes()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_contentTypes[$id])) {
            if ($this->headers->get('Accept') !== null) {
                $this->_contentTypes[$id] = $this->parseAcceptHeader($this->headers->get('Accept'));
            } else {
                $this->_contentTypes[$id] = [];
            }
        }
        return $this->_contentTypes[$id];
    }

    /**
     * Sets the acceptable content types.
     * Please refer to [[getAcceptableContentTypes()]] on the format of the parameter.
     * @param array $value the content types that are acceptable by the end user. They should
     * be ordered by the preference level.
     * @see getAcceptableContentTypes()
     * @see parseAcceptHeader()
     */
    public function setAcceptableContentTypes($value)
    {
        $this->_contentTypes[CoroHelper::getId()] = $value;
    }

    private $_languages;

    /**
     * Returns the languages acceptable by the end user.
     * This is determined by the `Accept-Language` HTTP header.
     * @return array the languages ordered by the preference level. The first element
     * represents the most preferred language.
     */
    public function getAcceptableLanguages()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_languages[$id])) {
            if ($this->headers->has('Accept-Language')) {
                $this->_languages[$id] = array_keys($this->parseAcceptHeader($this->headers->get('Accept-Language')));
            } else {
                $this->_languages[$id] = [];
            }
        }
        return $this->_languages[$id];
    }

    /**
     * @param array $value the languages that are acceptable by the end user. They should
     * be ordered by the preference level.
     */
    public function setAcceptableLanguages($value)
    {
        $id = CoroHelper::getId();
        $this->_languages[$id] = $value;
    }


    /**
     * Returns the cookie collection.
     * Through the returned cookie collection, you may access a cookie using the following syntax:
     *
     * ~~~
     * $cookie = $request->cookies['name']
     * if ($cookie !== null) {
     *     $value = $cookie->value;
     * }
     *
     * // alternatively
     * $value = $request->cookies->getValue('name');
     * ~~~
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_cookies[$id])) {
            $this->_cookies[$id] = new CookieCollection($this->loadCookies(), [
                'readOnly' => true,
            ]);
        }
        return $this->_cookies[$id];
    }

    private $_csrfToken;

    /**
     * Returns the token used to perform CSRF validation.
     *
     * This token is a masked version of [[rawCsrfToken]] to prevent [BREACH attacks](http://breachattack.com/).
     * This token may be passed along via a hidden field of an HTML form or an HTTP header value
     * to support CSRF validation.
     * @param boolean $regenerate whether to regenerate CSRF token. When this parameter is true, each time
     * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
     * @return string the token used to perform CSRF validation.
     */
    public function getCsrfToken($regenerate = false)
    {
        $id = CoroHelper::getId();
        if (!isset($this->_csrfToken[$id]) || $regenerate) {
            $token = $this->loadCsrfToken();
            if ($regenerate || empty($token)) {
                $token = $this->generateCsrfToken();
            }
            $this->_csrfToken[$id] = Yii::$app->security->maskToken($token);
        }
        return $this->_csrfToken[$id];
    }

    private $_traceId;

    /**
     * Returns the traceId.
     * @return string|null traceId, null if not available
     */
    public function getTraceId()
    {
        $id = CoroHelper::getId();
        if (!isset($this->_traceId[$id])) {
            $this->_traceId[$id] = Yii::$app->BaseHelper->guid();
        }
        return $this->_traceId[$id];
    }

    public function setTraceId($tid)
    {
        $id = CoroHelper::getId();
        $this->_traceId[$id] = $tid;
    }


    public function release()
    {
        $this->clear();
    }
}

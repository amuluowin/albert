<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午9:28
 */

namespace yii\swoole\governance\provider;

use Yii;
use yii\helpers\VarDumper;
use yii\httpclient\Client;
use yii\swoole\base\Output;
use yii\swoole\consul\ConsulClient;
use yii\swoole\web\Response;

class ConsulProvider extends BaseProvider implements ProviderInterface
{
    /**
     * Register path
     */
    const REGISTER_PATH = '/v1/agent/service/register';

    /**
     * Discovery path
     */
    const DISCOVERY_PATH = '/v1/health/service/';

    const DEREGISTER_PATH = '/v1/agent/service/deregister/';

    /**
     * @var array
     */
    public $register;

    /**
     * @var array
     */
    public $discovery;

    /**
     * @var int
     */
    public $checkType = 1;

    /**
     * @var string
     */
    public $servicePrefix = 'service-';

    /**
     * @var string
     */
    public $apiPrefix = 'api-';

    /**
     * @var array
     */
    private $services = [];

    /**
     * @var array
     */
    private $apis = [];

    const HTTP = 1;
    const DNS = 2;

    /**
     * @var ConsulClient
     */
    public $client;

    public function init()
    {
        parent::init();
        $this->services = array_keys(Yii::$rpcList);
        $this->apis = Yii::$apis;
        if (!$this->client instanceof ConsulClient) {
            $this->client = Yii::$app->consul;
        }
    }

    public function getServices(string $serviceName, string $preFix)
    {
        $nodes = $this->getServiceFromCache($serviceName);
        if ($nodes) {
            return $nodes;
        } else {
            $nodes = $this->get($serviceName, $preFix);
            $this->setServiceToCache([$serviceName => $nodes]);
            return $nodes;
        }
    }

    private function get(string $serviceName, string $preFix)
    {
        $url = $this->getDiscoveryUrl($serviceName, $preFix);
        $services = Yii::$app->httpclient->get($url)->send()->getData();
        $nodes = [];
        if (is_array($services)) {
            // 数据格式化
            foreach ($services as $service) {
                if (!isset($service['Service'])) {
                    Yii::warning("consul[Service] 服务健康节点集合，数据格式不不正确，Data=" . VarDumper::export($services));
                    continue;
                }
                $serviceInfo = $service['Service'];
                if (!isset($serviceInfo['Address'], $serviceInfo['Port'])) {
                    Yii::warning("consul[Address] Or consul[Port] 服务健康节点集合，数据格式不不正确，Data=" . VarDumper::export($services));
                    continue;
                }
                if (isset($service['Checks'])) {
                    foreach ($service['Checks'] as $check) {
                        if ($check['ServiceName'] === $preFix . $serviceName) {
                            if ($check['Status'] === 'passing') {
                                $address = $serviceInfo['Address'];
                                $port = $serviceInfo['Port'];
                                $nodes[] = [$address, $port];
                            } else {
                                $url = sprintf('%s:%d%s%s', $this->client->address, $this->client->port, self::DEREGISTER_PATH, $check['ServiceID']);
                                $this->deRegisterService($url);
                            }
                        }
                    }
                }
            }
        } else {
            Output::writeln(sprintf("can not find service %s from consul:%s:%d", $serviceName, $this->client->address, $this->client->port), Output::LIGHT_RED);
        }
        return $nodes;
    }

    /**
     * register service
     *
     * @param array ...$params
     *
     * @return bool
     */
    public function registerService(...$params): bool
    {
        $url = sprintf('%s:%d%s', $this->client->address, $this->client->port, self::REGISTER_PATH);
        $result = true;
        foreach ($this->services as $service) {
            $service = $this->servicePrefix . $service;
            $id = sprintf('%s-%s', APP_NAME, $service);
            $this->register['ID'] = $id;
            $this->register['Name'] = $service;
            $this->register['Port'] = intval(Yii::$app->params['swoole']['rpc']['port']);
            $this->register['Check']['id'] = $id;
            $this->register['Check']['tcp'] = sprintf('%s:%d', LocalIP, Yii::$app->params['swoole']['rpc']['port']);
            $this->register['Check']['name'] = $service;
            $result &= $this->putService($url);
        }

        foreach ($this->apis as $api) {
            $api = $this->apiPrefix . $api;
            $id = sprintf('%s-%s', APP_NAME, $api);
            $this->register['ID'] = $id;
            $this->register['Name'] = $api;
            $this->register['Port'] = intval(Yii::$app->params['swoole']['web']['port']);
            $this->register['Check']['id'] = $id;
            $this->register['Check']['tcp'] = sprintf('%s:%d', LocalIP, Yii::$app->params['swoole']['rpc']['port']);
            $this->register['Check']['name'] = $api;
            $result &= $this->putService($url);
        }

        return $result;
    }

    private function putService(string $url): bool
    {
        /**
         * @var Response $response
         */
        $response = Yii::$app->httpclient->put($url, $this->register)->setFormat(Client::FORMAT_JSON)->send();
        $output = 'RPC register service %s %s by consul tcp=%s:%d.';
        if ($response->getIsOk()) {
            Output::writeln(sprintf($output, $this->register['Name'], 'success', $this->register['Address'], $this->register['Port']), Output::LIGHT_GREEN);
            return true;
        } else {
            Output::writeln(sprintf($output . 'error=%s', $this->register['Name'], 'failed', $this->register['Address'], $this->register['Port'], $response->getContent()), Output::LIGHT_RED);
            return false;
        }
    }

    private function deRegisterService(string $url): bool
    {
        /**
         * @var Response $response
         */
        $response = Yii::$app->httpclient->put($url)->setFormat(Client::FORMAT_JSON)->send();
        $output = 'RPC deregister service %s %s by consul tcp=%s:%d.';
        if ($response->getIsOk()) {
            Output::writeln(sprintf($output, $this->register['Name'], 'success', $this->register['Address'], $this->register['Port']), Output::LIGHT_GREEN);
            return true;
        } else {
            Output::writeln(sprintf($output . 'error=%s', $this->register['Name'], 'failed', $this->register['Address'], $this->register['Port'], $response->getContent()), Output::LIGHT_RED);
            return false;
        }
    }

    /**
     * @param string $serviceName
     *
     * @return string
     */
    private function getDiscoveryUrl(string $serviceName, string $preFix): string
    {
        $serviceName = $preFix . $serviceName;
        $query = array_filter([
            'passing' => $this->discovery['passing'],
            'dc' => $this->discovery['dc'],
            'near' => $this->discovery['near'],
        ]);

        if (!empty($this->register['Tags'])) {
            $query['tag'] = $this->register['Tags'];
        }

        $queryStr = http_build_query($query);
        $path = sprintf('%s%s', self::DISCOVERY_PATH, $serviceName);

        return sprintf('%s:%d%s?%s', $this->client->address, $this->client->port, $path, $queryStr);
    }

    public function dnsCheck()
    {
        if (!empty($this->services)) {
            foreach ($this->services as $service) {
                $this->check($service, $this->servicePrefix);
            }
            foreach ($this->apis as $api) {
                $this->check($api, $this->apiPrefix);
            }
        } else {
            $this->check($this->register['Name']);
        }
    }

    private function check(string $service, string $preFix)
    {
        if ($this->checkType === self::DNS) {
            $dns = sprintf('%s.service.$s.consul', $preFix . $service, $this->discovery['dc']);
            $node[$service] = \Co::getaddrinfo($dns);
        } else {
            if ($preFix === $this->servicePrefix) {
                $node[$service] = $this->get($service, $preFix);
            } else {
                $node['/' . $service] = $this->get($service, $preFix);
            }
        }
        $this->setServiceToCache($node);
    }
}
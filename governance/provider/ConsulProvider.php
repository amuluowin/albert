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
        $nodes = $this->getServiceFromCache($serviceName, $preFix);
        if ($nodes) {
            return $nodes;
        } else {
            return $this->get($serviceName, $preFix);
        }
    }

    private function get(string $serviceName, string $preFix)
    {
        $url = $this->getDiscoveryUrl($serviceName, $preFix);
        $services = Yii::$app->consul->httpClient->get($url)->send()->getData();
        if (is_array($services)) {
            // 数据格式化
            $nodes = [];
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
                $address = $serviceInfo['Address'];
                $port = $serviceInfo['Port'];
                $nodes[] = [$address, $port];
            }

            return $nodes;
        } else {
            Output::writeln(sprintf("can not find service %s from consul:%s:%d", $serviceName, $this->client->address, $this->client->port), Output::LIGHT_RED);
        }
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
            $id = sprintf('service-%s-%s', $this->register['Check']['tcp'], $service);
            $this->register['ID'] = $id;
            $this->register['Name'] = $service;
            $this->register['Check']['id'] = $id;
            $this->register['Check']['name'] = $service;
            $result &= $this->putService($url);
        }

        foreach ($this->apis as $api) {
            $api = $this->apiPrefix . $api;
            $id = sprintf('api-%s-%s', $this->register['Check']['tcp'], $api);
            $this->register['ID'] = $id;
            $this->register['Name'] = $api;
            $this->register['Port'] = 80;
            $this->register['Check']['id'] = $id;
            $this->register['Check']['name'] = $api;
            $result &= $this->putService($url);
        }

        return $result;
    }

    private function putService(string $url): bool
    {
        $response = Yii::$app->consul->httpClient->put($url, $this->register)->setFormat(Client::FORMAT_JSON)->send();
        $output = 'RPC register service %s %s by consul tcp=%s:%d';
        if (empty($result) && $response->getStatusCode() == 200) {
            Output::writeln(sprintf($output, $this->register['Name'], 'success', $this->register['Address'], $this->register['Port']), Output::LIGHT_GREEN);
            return true;
        } else {
            Output::writeln(sprintf($output, $this->register['Name'], 'failed', $this->register['Address'], $this->register['Port']), Output::LIGHT_RED);
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
        $query = [
            'passing' => $this->discovery['passing'],
            'dc' => $this->discovery['dc'],
            'near' => $this->discovery['near'],
        ];

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
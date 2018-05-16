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
use yii\swoole\files\OutPut;

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
     * @var string
     */
    public $address = "http://127.0.0.1";

    /**
     * @var int
     */
    public $port = 8500;

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
     * @var array
     */
    private $services = [];

    const HTTP = 1;
    const DNS = 2;

    public function init()
    {
        parent::init();
        if (empty($this->register['Name'])) {
            $this->services = array_keys(Yii::$rpcList);
        }
    }

    public function getServices(string $serviceName, ...$params)
    {
        $nodes = $this->getServiceFromCache($serviceName);
        if ($nodes) {
            return $nodes;
        } else {
            return $this->get($serviceName, $params);
        }
    }

    private function get(string $serviceName, ...$params)
    {
        $url = $this->getDiscoveryUrl($serviceName);
        $services = Yii::$app->httpclient->get($url)->send()->getData();
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
            print_r(sprintf("can not connect to service provider consul:%s:%d", $this->address, $this->port) . PHP_EOL);
        }
    }

    /**
     * register service
     *
     * @param array ...$params
     *
     * @return bool
     */
    public function registerService(...$params)
    {
        $url = sprintf('%s:%d%s', $this->address, $this->port, self::REGISTER_PATH);
        if (!empty($this->services)) {
            foreach ($this->services as $service) {
                $this->register['Name'] = $service;
                $this->putService($url);
            }
        } else {
            $this->putService($url);
        }

        return true;
    }

    private function putService(string $url)
    {
        $response = Yii::$app->httpclient->put($url, $this->register)->setFormat(Client::FORMAT_JSON)->send();
        $output = 'RPC service register service %s %s by consul ! tcp=%s:%d';
        if (empty($result) && $response->getStatusCode() == 200) {
            print_r(sprintf($output, $this->register['Name'], 'success', $this->register['Address'], $this->register['Port']) . PHP_EOL);
        } else {
            print_r(sprintf($output, $this->register['Name'], 'failed', $this->register['Address'], $this->register['Port']) . PHP_EOL);
        }
    }

    /**
     * @param string $serviceName
     *
     * @return string
     */
    private function getDiscoveryUrl(string $serviceName): string
    {
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

        return sprintf('%s:%d%s?%s', $this->address, $this->port, $path, $queryStr);
    }

    public function dnsCheck()
    {
        if (!empty($this->services)) {
            foreach ($this->services as $service) {
                $this->check($service);
            }
        } else {
            $this->check($this->register['Name']);
        }
    }

    private function check(string $service)
    {
        if ($this->checkType === self::DNS) {
            $dns = sprintf('%s.service.$s.consul', $service, $this->discovery['dc']);
            $node[$service] = \Co::getaddrinfo($dns);
        } else {
            $node[$service] = $this->get($service);
        }
        $this->setServiceToCache($node);
    }
}
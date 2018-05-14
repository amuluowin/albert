<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-14
 * Time: 下午9:28
 */

namespace yii\swoole\governance\provider;

use Yii;
use yii\base\Component;
use yii\helpers\VarDumper;
use yii\httpclient\Client;

class ConsulProvider extends Component implements ProviderInterface
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

    public function init()
    {
        parent::init();
        if (empty($this->register['Tags'])) {
            $this->register['Tags'] = array_keys(Yii::$rpcList);
        }
    }

    public function getServices(string $serviceName, string $tag = null)
    {
        $url = $this->getDiscoveryUrl($serviceName, $tag);
        $services = Yii::$app->httpclient->get($url)->send()->getData();

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
        $result = Yii::$app->httpclient->put($url, $this->register)->setFormat(Client::FORMAT_JSON)->send()->getData();
        if (empty($result)) {
            print_r(sprintf('RPC service register success by consul ! tcp=%s:%d', $this->register['Address'], $this->register['Port']) . PHP_EOL);
        }

        return true;
    }

    /**
     * @param string $serviceName
     *
     * @return string
     */
    private function getDiscoveryUrl(string $serviceName, string $tag = null): string
    {
        $query = [
            'passing' => $this->discovery['passing'],
            'dc' => $this->discovery['dc'],
            'near' => $this->discovery['near'],
        ];

        if (!empty($tag)) {
            $query['tag'] = $tag;
        }

        $queryStr = http_build_query($query);
        $path = sprintf('%s%s', self::DISCOVERY_PATH, $serviceName);

        return sprintf('%s:%d%s?%s', $this->address, $this->port, $path, $queryStr);
    }
}
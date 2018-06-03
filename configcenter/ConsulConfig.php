<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-06-02
 * Time: 22:13
 */

namespace yii\swoole\configcenter;

use Yii;
use yii\base\Component;
use yii\swoole\base\Output;
use yii\swoole\consul\ConsulClient;
use yii\swoole\consul\ConsulTrait;
use yii\httpclient\Client;

class ConsulConfig extends Component implements ConfigInterface
{
    use ConsulTrait;

    /**
     * @var string
     */
    public $dc = 'dc1';

    /**
     * KV path
     */
    const KV_PATH = '/v1/kv';

    public function putConfig(string $key, array $config, Client $client = null)
    {
        /**
         * @var Client
         */
        if ($client === null) {
            $client = Yii::$app->consul->httpClient;
        }
        $respones = $client->put($this->getPath($key), $config)->setFormat(Client::FORMAT_JSON)->send();
        if ($respones->getData() != true) {
            Output::writeln(sprintf('can not put config to consul %s:%d', $this->client->address, $this->client->port), Output::LIGHT_RED);
        }
    }

    public function getConfig(string $key, Client $client = null)
    {
        /**
         * @var Client
         */
        if ($client === null) {
            $client = Yii::$app->consul->httpClient;
        }
        $respones = $client->get($this->getPath($key))->setFormat(Client::FORMAT_JSON)->send();
        if ($respones->getStatusCode() == 404) {
            Output::writeln(sprintf('can not get config %s from consul', $key), Output::LIGHT_RED);
            return null;
        }
        $result = $respones->getData();
        $data = [];
        foreach ($result as $config) {
            if (isset(Yii::$configs[$config['Key']]) && Yii::$configs[$config['Key']] === $config['Value']) {
                continue;
            }
            Yii::$configs[$config['Key']] = $config['Value'];
            $data[$config['Key']] = json_decode(base64_decode($config['Value']), true);
        }

        return $data;
    }

    private function getPath(string $key)
    {
        return sprintf('%s:%d%s', $this->client->address, $this->client->port, self::KV_PATH . '/' . $key);
    }
}
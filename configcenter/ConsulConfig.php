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
use yii\swoole\consul\ConsulTrait;
use yii\swoole\httpclient\Client;

class ConsulConfig extends Component implements ConfigInterface
{
    use ConsulTrait;

    /**
     * @var int
     */
    public $retry = 3;

    /**
     * @var int
     */
    public $sleep = 1;

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
            $client = Yii::$app->httpclient;
        }
        for ($i = 0; $i < $this->retry; $i++) {
            $respones = $client->put($this->getPath($key), $config)->setFormat(Client::FORMAT_JSON)->send();
            if (!$respones->getIsOk()) {
                Output::writeln(sprintf('can not put config %s to consul %s:%d', $key, $this->client->address, $this->client->port), Output::LIGHT_RED);
                \Co::sleep($this->sleep);
            } else {
                Output::writeln(sprintf('put config %s to consul %s:%d success', $key, $this->client->address, $this->client->port), Output::LIGHT_GREEN);
                break;
            }
        }
    }

    public function delConfig(string $key, Client $client = null)
    {
        /**
         * @var Client
         */
        if ($client === null) {
            $client = Yii::$app->httpclient;
        }
        for ($i = 0; $i < $this->retry; $i++) {
            $respones = $client->delete($this->getPath($key))->setFormat(Client::FORMAT_JSON)->send();
            if (!$respones->getIsOk()) {
                Output::writeln(sprintf('can not delete config %s to consul %s:%d', $key, $this->client->address, $this->client->port), Output::LIGHT_RED);
                \Co::sleep($this->sleep);
            } else {
                Output::writeln(sprintf('delete config %s to consul %s:%d success', $key, $this->client->address, $this->client->port), Output::LIGHT_GREEN);
                break;
            }
        }
    }

    public function getConfig(string $key, Client $client = null)
    {
        /**
         * @var Client
         */
        if ($client === null) {
            $client = Yii::$app->httpclient;
        }
        $respones = $client->get($this->getPath($key))->setFormat(Client::FORMAT_JSON)->send();
        if ($respones->getStatusCode() == 404) {
            Output::writeln(sprintf('can not get config %s from consul', $key), Output::LIGHT_RED);
            return null;
        }
        $result = $respones->getData();
        $data = [];
        foreach ($result as $config) {
            $config['Key'] = str_replace(APP_NAME . '/', '', $config['Key']);
            $data[$config['Key']] = json_decode(base64_decode($config['Value']), true);
        }

        return $data;
    }

    private function getPath(string $key)
    {
        return sprintf('%s:%d%s', $this->client->address, $this->client->port, self::KV_PATH . '/' . APP_NAME . '/' . $key);
    }
}
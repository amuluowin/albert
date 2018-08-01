<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-16
 * Time: 下午5:49
 */

namespace yii\swoole\governance\provider;

use Yii;
use yii\base\Component;

class BaseProvider extends Component
{
    public function getServiceFromCache(string $service): array
    {
        $result = Yii::$app->cache->get($service);
        return is_array($result) ? $result : [];
    }

    protected function setServiceToCache(array $services)
    {
        if ($services && is_array($services)) {
            foreach ($services as $service => $node) {
                Yii::$app->cache->set($service, $node);
            }
        }
    }

    public function delService(string $service): bool
    {
        return Yii::$app->cache->delete($service);
    }
}
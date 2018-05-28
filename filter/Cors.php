<?php
/**
 * Created by PhpStorm.
 * User: 76587
 * Date: 2018-05-28
 * Time: 12:04
 */

namespace yii\swoole\filter;

use Yii;

class Cors extends \yii\filters\Cors
{
    public function extractHeaders()
    {
        $headers = [];
        $request = Yii::$app->getRequest();
        foreach (array_keys($this->cors) as $headerField) {
            $headerData = $request->headers->has($headerField) ? $request->headers->get($headerField) : null;
            if ($headerData !== null) {
                $headers[$headerField] = $headerData;
            }
        }
        return $headers;
    }
}
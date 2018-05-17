<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午8:35
 */

namespace yii\swoole\governance\exporter;


interface ExportInterface
{
    public function export($data, string $key = null);
}
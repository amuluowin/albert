<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午4:20
 */

namespace yii\swoole\moniter\Exporter;

interface ExporterInterface
{
    public function export(string $data);

    public function open();

    public function close();
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午4:20
 */

namespace yii\swoole\moniter\Writer;

interface WriterInterface
{
    public function write(string $data);

    public function open();

    public function close();
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午2:42
 */

namespace yii\swoole\moniter\Reader;

use Yii;

interface ReaderInterface
{
    public function read(): ?string;

    public function open();

    public function close();
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午4:40
 */

namespace yii\swoole\moniter\Parser;

use Yii;

interface ParserInterface
{
    public function parse($content);
}
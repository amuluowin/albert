<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-2
 * Time: 下午4:41
 */

namespace yii\swoole\moniter\Parser;

use Yii;
use yii\swoole\base\Channel;

class NotParser extends Channel implements ParserInterface
{

    public function parse($content)
    {
        return $content;
    }
}
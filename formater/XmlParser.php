<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\formater;

use yii\base\BaseObject;

/**
 * XmlParser parses HTTP message content as XML.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class XmlParser extends BaseObject
{
    /**
     * @inheritdoc
     */
    public function parse($xml)
    {
        return $this->convertXmlToArray($xml);
    }

    /**
     * Converts XML document to array.
     * @param string|\SimpleXMLElement $xml xml to process.
     * @return array XML array representation.
     */
    protected function convertXmlToArray($xml)
    {
        if (!is_object($xml)) {
            $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        $result = (array)$xml;
        foreach ($result as $key => $value) {
            if (is_object($value)) {
                $result[$key] = $this->convertXmlToArray($value);
            }
        }
        return $result ? $result : '';
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 17-8-23
 * Time: 下午6:11
 */

namespace yii\swoole\web;


use yii\web\BadRequestHttpException;
use yii\web\RequestParserInterface;

class XmlParser implements RequestParserInterface
{
    public $throwException = true;

    /**
     * Parses a HTTP request body.
     * @param string $rawBody the raw HTTP request body.
     * @param string $contentType the content type specified for the request body.
     * @return array parameters parsed from the request body
     */
    public function parse($rawBody, $contentType)
    {
        return $this->convertXmlToArray($rawBody);
    }

    /**
     * Converts XML document to array.
     * @param string|\SimpleXMLElement $xml xml to process.
     * @return array XML array representation.
     */
    protected function convertXmlToArray($xml)
    {
        try {
            if (!is_object($xml)) {
                $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            }
            $result = (array)$xml;
            foreach ($result as $key => $value) {
                if (is_object($value)) {
                    $result[$key] = $this->convertXmlToArray($value);
                }
            }
            return $result;
        } catch (\Exception $e) {
            if ($this->throwException) {
                throw new BadRequestHttpException('Invalid XML data in request body: ' . $e->getMessage());
            }
            return [];
        }

    }
}
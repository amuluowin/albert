<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\swoole\formater;

use DOMDocument;
use DOMElement;
use DOMText;
use SimpleXMLElement;
use yii\base\Arrayable;
use yii\base\BaseObject;
use yii\helpers\StringHelper;

/**
 * XmlFormatter formats HTTP message as XML.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class XmlFormatter extends BaseObject
{
    /**
     * @var string the XML version
     */
    public $version = '1.0';
    /**
     * @var string the XML encoding. If not set, it will use the value of [[\yii\base\Application::charset]].
     */
    public $encoding = 'UTF-8';
    /**
     * @var string the name of the root element.
     */
    public $rootTag = 'root';
    /**
     * @var string the name of the elements that represent the array elements with numeric keys.
     * @since 2.0.1
     */
    public $itemTag = 'item';

    /**
     * @var bool whether to interpret objects implementing the [[\Traversable]] interface as arrays.
     * Defaults to `true`.
     * @since 2.0.1
     */
    public $useTraversableAsArray = true;


    /**
     * @inheritdoc
     */
    public function format(array $data)
    {
        if ($data !== null) {
            if ($data instanceof DOMDocument) {
                $content = $data->saveXML();
            } elseif ($data instanceof SimpleXMLElement) {
                $content = $data->saveXML();
            } else {
                $dom = new DOMDocument($this->version, $this->encoding);
                $root = new DOMElement($this->rootTag);
                $dom->appendChild($root);
                $this->buildXml($root, $data);
                $content = $dom->saveXML();
            }
        }

        return $content;
    }

    /**
     * @param DOMElement $element
     * @param mixed $data
     */
    protected function buildXml($element, $data)
    {
        if (is_array($data) ||
            ($data instanceof \Traversable && $this->useTraversableAsArray && !$data instanceof Arrayable)
        ) {
            if ($data) {
                foreach ($data as $name => $value) {
                    if (is_int($name) && is_object($value)) {
                        $this->buildXml($element, $value);
                    } elseif (is_array($value) || is_object($value)) {
                        $child = new DOMElement(is_int($name) ? $this->itemTag : $name);
                        $element->appendChild($child);
                        $this->buildXml($child, $value);
                    } else {
                        $child = new DOMElement(is_int($name) ? $this->itemTag : $name);
                        $element->appendChild($child);
                        $child->appendChild(new DOMText((string)$value));
                    }
                }
            } else {
                $element->appendChild(new DOMText((string)null));
            }

        } elseif (is_object($data)) {
            $child = new DOMElement(StringHelper::basename(get_class($data)));
            $element->appendChild($child);
            if ($data instanceof Arrayable) {
                $this->buildXml($child, $data->toArray());
            } else {
                $array = [];
                foreach ($data as $name => $value) {
                    $array[$name] = $value;
                }
                $this->buildXml($child, $array);
            }
        } else {
            $element->appendChild(new DOMText((string)$data));
        }
    }
}
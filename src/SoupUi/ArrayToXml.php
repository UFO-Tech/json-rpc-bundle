<?php

namespace Ufo\JsonRpcBundle\SoupUi;



use DOMDocument;

class ArrayToXml
{

    const XML_VERSION = '1.0';
    const XML_ENCODING = 'utf-8';

    /**
     * The root DOM Document.
     *
     * @var DOMDocument
     */
    protected $document;

    /**
     * Construct a new instance.
     * @param $rootNode
     * @param array $array
     * @param array $rootAttributes
     * @throws \DOMException
     */
    public function __construct($rootNode, array $array, array $rootAttributes = [])
    {
        $this->document = new \DOMDocument(static::XML_VERSION, static::XML_ENCODING);

        if ($this->isArrayAllKeySequential($array) && !empty($array)) {
            throw new \DOMException('Invalid Character Error');
        }

        $root = $this->document->createElement($rootNode);

        foreach ($rootAttributes as $key => $value) {
            $root->setAttribute($key, $value);
        }

        $this->document->appendChild($root);

        $this->convertElement($root, $array);
    }

    /**
     * Convert the given array to an xml string.
     *
     * @param $rootNode
     * @param array $array
     * @param array $rootAttributes

     * @return string
     */
    public static function convert($rootNode, array $array, array $rootAttr = [])
    {
        $converter = new static($rootNode, $array, $rootAttr);

        return $converter->toXml();
    }

    /**
     * Return as XML.
     *
     * @return string
     */
    public function toXml()
    {
        return $this->document->saveXML();
    }

    /**
     * Parse individual element.
     *
     * @param \DOMElement $element
     * @param string|string[] $value
     */
    private function convertElement(\DOMElement $element, $value)
    {
        $sequential = $this->isArrayAllKeySequential($value);

        if (!is_array($value)) {
            $element->nodeValue = htmlspecialchars($value);

            return;
        }

        foreach ($value as $key => $data) {
            $prefix = null;
            if (isset($data['@ns'])) {
                $prefix = $data['@ns'];
                unset($data['@ns']);
            }
            $key = $prefix . $key;
            if (!$sequential) {
                if (($key === '_attributes') || ($key === '@attributes')) {
                    $this->addAttributes($element, $data);
                } elseif ((($key === '_value') || ($key === '@value')) && is_string($data)) {
                    $element->nodeValue = htmlspecialchars($data);
                } else {
                    $this->addNode($element, $key, $data);
                }
            } elseif (is_array($data)) {
                $this->addCollectionNode($element, $data);
            } else {
                $this->addSequentialNode($element, $data);
            }
        }
    }

    /**
     * Add node.
     *
     * @param \DOMElement $element
     * @param string $key
     * @param string|string[] $value
     */
    protected function addNode(\DOMElement $element, $key, $value)
    {
        $key = str_replace(' ', '_', $key);

        $child = $this->document->createElement($key);
        $element->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * Add collection node.
     *
     * @param \DOMElement $element
     * @param string|string[] $value
     *
     * @internal param string $key
     */
    protected function addCollectionNode(\DOMElement $element, $value)
    {
        if ($element->childNodes->length == 0) {
            $this->convertElement($element, $value);
            return;
        }

        $child = $element->cloneNode();
        $element->parentNode->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * Add sequential node.
     *
     * @param \DOMElement $element
     * @param string|string[] $value
     *
     * @internal param string $key
     */
    protected function addSequentialNode(\DOMElement $element, $value)
    {
        if (empty($element->nodeValue)) {
            $element->nodeValue = htmlspecialchars($value);
            return;
        }

        $child = $element->cloneNode();
        $child->nodeValue = htmlspecialchars($value);
        $element->parentNode->appendChild($child);
    }

    /**
     * Check if array are all sequential.
     *
     * @param array|string $value
     *
     * @return bool
     */
    protected function isArrayAllKeySequential($value)
    {
        if (!is_array($value)) {
            return false;
        }

        if (count($value) == 0) {
            return true;
        }

        return array_unique(array_map('is_int', array_keys($value))) === [true];
    }

    /**
     * Add attributes.
     *
     * @param \DOMElement $element
     * @param string[] $data
     */
    protected function addAttributes($element, $data)
    {
        foreach ($data as $attrKey => $attrVal) {
            $element->setAttribute($attrKey, $attrVal);
        }
    }

}
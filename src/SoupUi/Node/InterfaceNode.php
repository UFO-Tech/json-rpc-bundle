<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 12:08
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\IHaveChildNodes;
use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;
use Ufo\JsonRpcBundle\SoupUi\Node\Traits\HaveChildNodesTrait;

class InterfaceNode implements ISoupUiNode, IHaveChildNodes
{

    use HaveChildNodesTrait;

    const NODE_NAME = 'interface';

    protected array $attributes = [
        'xsi:type' => 'con:RestService',
        'wadlVersion' => 'http://wadl.dev.java.net/2009/02',
        'name' => 'JsonRPC',
        'type' => 'rest',
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
    ];

    /**
     * RootNode constructor.
     * @param array $attributes
     * @param array $nodes
     */
    public function __construct(array $attributes = [], array $nodes = [])
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        $this->setChild($nodes);
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return string
     */
    public function getTag(): string
    {
        return static::NODE_NAME;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $content = [
            '@ns' => static::SOUPUI_NS,
            '@attributes' => $this->getAttributes(),
        ];

        foreach ($this->getChildNodes() as $node) {
            $content = array_merge($content, $node->toArray());
        }

        return [
            $this->getTag() => $content
        ];
    }

}
<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 13:40
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\IHaveChildNodes;
use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;
use Ufo\JsonRpcBundle\SoupUi\Node\Traits\HaveChildNodesTrait;

class ResourceNode implements ISoupUiNode, IHaveChildNodes
{

    use HaveChildNodesTrait;

    /**
     * @var array
     */
    protected $attributes = [
        'path' => '/api',
        'name' => '',
    ];

    /**
     * EndpointsNode constructor.
     * @param array $attributes
     * @param array $nodes
     */
    public function __construct(array $attributes = [], $nodes = [])
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        $this->setChild($nodes);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return 'resource';
    }

    /**
     * @return array
     */
    public function toArray()
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
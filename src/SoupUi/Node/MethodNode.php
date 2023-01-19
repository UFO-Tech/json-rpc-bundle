<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 16:26
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\IHaveChildNodes;
use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;
use Ufo\JsonRpcBundle\SoupUi\Node\Traits\HaveChildNodesTrait;
use Laminas\Http\Request;

class MethodNode implements ISoupUiNode, IHaveChildNodes
{

    use HaveChildNodesTrait;

    /**
     * @var array
     */
    protected $attributes = [
        'method' => Request::METHOD_GET,
        'name' => '',
    ];

    /**
     * EndpointsNode constructor.
     * @param array $attributes
     * @param array $nodes
     */
    public function __construct(array $attributes = [], $nodes = [])
    {
        $this->attributes['name'] = 'method_' . uniqid();
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
        return '';
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $content = [];
        foreach ($this->getChildNodes() as $node) {
            $content = array_merge($content, $node->toArray());
        }

        return [
            '@attributes' => $this->getAttributes(),
            'request' => $content
        ];
    }

}
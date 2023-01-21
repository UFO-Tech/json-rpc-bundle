<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 12:08
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\IHaveChildNodes;
use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;
use Ufo\JsonRpcBundle\SoupUi\Node\Traits\HaveChildNodesTrait;

class MethodsNode implements ISoupUiNode, IHaveChildNodes
{

    use HaveChildNodesTrait;

    const NODE_NAME = 'method';

    /**
     * RootNode constructor.
     * @param array $nodes
     */
    public function __construct(array $nodes = [])
    {
        $this->setChild($nodes);
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return [];
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
        ];

        foreach ($this->getChildNodes() as $node) {
            $content[] = $node->toArray();
        }

        return [
            $this->getTag() => $content
        ];
    }
}
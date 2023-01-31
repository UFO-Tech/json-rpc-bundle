<?php

namespace Ufo\JsonRpcBundle\SoupUi\Node\Traits;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

trait HaveChildNodesTrait
{

    /**
     * @var array
     */
    protected array $child = [];

    /**
     * @param ISoupUiNode $node
     * @return $this
     */
    public function addChildNode(ISoupUiNode $node): static
    {
        $this->child[] = $node;
        return $this;
    }

    /**
     * @return ISoupUiNode[]
     */
    public function getChildNodes(): array
    {
        return $this->child;
    }

    /**
     * @param ISoupUiNode[] $nodes
     * @return $this
     */
    protected function setChild(array $nodes): static
    {
        foreach ($nodes as $node) {
            $this->addChildNode($node);
        }
        return $this;
    }
}
<?php

namespace Ufo\JsonRpcBundle\SoupUi\Node\Interfaces;


interface IHaveChildNodes
{

    /**
     * @param ISoupUiNode $node
     * @return $this
     */
    public function addChildNode(ISoupUiNode $node): static;

    /**
     * @return ISoupUiNode[]
     */
    public function getChildNodes(): array;
}
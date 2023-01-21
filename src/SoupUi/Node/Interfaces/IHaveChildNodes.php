<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 12:28
 */

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
<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 09.06.2017
 * Time: 9:03
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node\Traits;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

trait HaveChildNodesTrait
{

    /**
     * @var array
     */
    protected $child = [];

    /**
     * @param ISoupUiNode $node
     * @return $this
     */
    public function addChildNode(ISoupUiNode $node)
    {
        $this->child[] = $node;
        return $this;
    }

    /**
     * @return ISoupUiNode[]
     */
    public function getChildNodes()
    {
        return $this->child;
    }

    /**
     * @param ISoupUiNode[] $nodes
     * @return $this
     */
    protected function setChild($nodes)
    {
        foreach ($nodes as $node) {
            $this->addChildNode($node);
        }
        return $this;
    }
}
<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 16:26
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ICanTransformToArray;
use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

class ParameterNode implements ICanTransformToArray
{
    /**
     * ParameterNode constructor.
     * @param string $name
     * @param string $value
     * @param string $style
     * @param string $default
     */
    public function __construct(protected string $name, protected string $value, protected string $style = 'HEADER', protected ?string $default = null)
    {
        $this->default = $this->default ?? $this->value;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return  [
            ISoupUiNode::SOUPUI_NS . 'name' => $this->name,
            ISoupUiNode::SOUPUI_NS . 'value' => $this->value,
            ISoupUiNode::SOUPUI_NS . 'style' => $this->style,
            ISoupUiNode::SOUPUI_NS . 'default' => $this->default,
        ];
    }
}
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
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var string
     */
    protected $style;

    /**
     * @var string
     */
    protected $default;

    /**
     * ParameterNode constructor.
     * @param string $name
     * @param string $value
     * @param string $style
     * @param string $default
     */
    public function __construct($name, $value, $style = 'HEADER', $default = null)
    {
        $this->name = $name;
        $this->value = $value;
        $this->style = $style;
        $this->default = $default ?: $value;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return  [
            ISoupUiNode::SOUPUI_NS . 'name' => $this->name,
            ISoupUiNode::SOUPUI_NS . 'value' => $this->value,
            ISoupUiNode::SOUPUI_NS . 'style' => $this->style,
            ISoupUiNode::SOUPUI_NS . 'default' => $this->default,
        ];
    }
}
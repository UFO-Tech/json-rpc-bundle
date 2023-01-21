<?php
/**
 * @author Doctor <ashterix69@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 13:40
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

class ParametersNode implements ISoupUiNode
{

    /**
     * @var array
     */
    protected array $parameters;

    /**
     * EndpointsNode constructor.
     * @param array $parameters
     */
    public function __construct(array $parameters = [])
    {
        foreach ($parameters as $parameter) {
            $this->addParameter($parameter);
        }
    }

    public function addParameter(ParameterNode $parameterNode)
    {
        $this->parameters[] = $parameterNode;
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
        return 'parameters';
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $parametersArray = [];
        foreach ($this->parameters as $parameter) {
            $parametersArray[] = $parameter->toArray();
        }

        return [
            $this->getTag() => [
                '@ns' => ISoupUiNode::SOUPUI_NS,
                'parameter' => [
                    '@ns' => ISoupUiNode::SOUPUI_NS,
                    [
                        $parametersArray,
                    ]
                ],
            ],
        ];
    }

}
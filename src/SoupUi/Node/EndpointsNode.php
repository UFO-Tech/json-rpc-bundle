<?php

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

class EndpointsNode implements ISoupUiNode
{
    /**
     * EndpointsNode constructor.
     * @param string $endpoint
     */
    public function __construct(protected string $endpoint)
    {
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
        return 'endpoints';
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            $this->getTag() => [
                '@ns' => ISoupUiNode::SOUPUI_NS,
                'endpoint' => [
                    '@ns' => ISoupUiNode::SOUPUI_NS,
                    [
                        $this->endpoint,
                    ]
                ],
            ],
        ];
    }

}
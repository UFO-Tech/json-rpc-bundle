<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 13:40
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

class EndpointsNode implements ISoupUiNode
{

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * EndpointsNode constructor.
     * @param string $endpoint
     */
    public function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return [];
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return 'endpoints';
    }

    /**
     * @return array
     */
    public function toArray()
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
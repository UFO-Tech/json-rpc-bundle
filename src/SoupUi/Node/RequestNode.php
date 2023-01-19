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

class RequestNode implements ISoupUiNode
{

    protected $attributes = [
        'name' => 'JsonRPC',
        'mediaType' => 'application/json',
    ];

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var array
     */
    protected $body;

    /**
     * RequestNode constructor.
     * @param $endpoint
     * @param array $attributes
     * @param array $body
     */
    public function __construct($endpoint, array $attributes = [], array $body = [])
    {
        $this->endpoint = $endpoint;
        $this->body = $body;
        $this->attributes['name'] = 'request_' . uniqid();
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return 'request';
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            '@ns' => ISoupUiNode::SOUPUI_NS,
           [
               '@attributes' => $this->getAttributes(),
               'endpoint' => [
                   '@ns' => ISoupUiNode::SOUPUI_NS,
                   [
                       $this->endpoint,
                   ]
               ],
               'request' => [
                   '@ns' => ISoupUiNode::SOUPUI_NS,
                   [
                       json_encode($this->body, JSON_PRETTY_PRINT),
                   ]
               ],
           ]
        ];
    }

}
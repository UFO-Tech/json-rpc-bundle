<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 10:04
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node;


use Ufo\JsonRpcBundle\SoupUi\Node\Interfaces\ISoupUiNode;

class RootNode implements ISoupUiNode
{
    const NODE_NAME = 'soapui-project';

    protected array $rootAttributes = [
        'xmlns:con' => self::SOUPUI_NS_URL,
        'soapui-version' => self::SOUPUI_VERSION,
        'runType' => 'SEQUENTIAL',
        'abortOnError' => 'false',
        'activeEnvironment' => 'Default',
        'name' => 'GeneratedProject',
    ];

    /**
     * RootNode constructor.
     * @param array $rootAttributes
     */
    public function __construct(array $rootAttributes = [])
    {
        $this->rootAttributes = array_merge($this->rootAttributes, $rootAttributes);
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->rootAttributes;
    }

    /**
     * @return string
     */
    public function getTag(): string
    {
        return static::NODE_NAME;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [];
    }
}
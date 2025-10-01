<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;


use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\RpcObject\RPC\DTO;

use Ufo\RpcObject\RPC\DTOCollection;
use function array_filter;
use function array_values;

class AttributesCollection
{
    /**
     * @var object[]
     */
    #[DTOCollection(IArrayConstructible::class)]
    protected array $attributes = [];

    public function getAttribute(string $attrFQCN, int $position = 0): ?object
    {
        $attributes = $this->getAttributes($attrFQCN);
        return $attributes[$position] ?? null;
    }

    public function getAttributes(string $attrFQCN): array
    {
        return array_values(array_filter($this->attributes, fn($attribute) => $attribute instanceof $attrFQCN));
    }

    public function addAttribute(object $attribute): static
    {
        $this->attributes[] = $attribute;

        return $this;
    }


}
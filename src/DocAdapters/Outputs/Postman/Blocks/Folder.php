<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

use function array_map;

final class Folder implements IPostmanBlock
{
    protected array $methods = [];

    public function __construct(
        protected string $name,
    ) {}

    public function addMethod(Method $method): self
    {
        $this->methods[] = $method;
        return $this;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'item' => array_map(fn(Method $method) => $method->toArray(), $this->methods)
        ];
    }
}
<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman;

use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Folder;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Info;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Variable;

use function array_map;
use function array_values;

class PostmanSpecFiller
{

    /**
     * @var Method[]
     */
    protected array $methods = [];

    /**
     * @var Folder[]
     */
    protected array $folders = [];

    /**
     * @var Variable[]
     */
    protected array $variables = [];

    public function __construct(protected Info $info) {}

    public function getInfo(): Info
    {
        return $this->info;
    }

    public function addMethod(Method $method): static
    {
        $this->methods[] = $method;
        return $this;
    }

    public function addToFolder(string $name, Method $method): static
    {
        if (!$folder = ($this->folders[$name] ?? null)) {
            $folder = new Folder($name);
            $this->folders[$name] = $folder;
        }
        $folder->addMethod($method);
        return $this;
    }

    public function addVariable(string $name, Variable $variable): static
    {
        $this->variables[$name] = $variable;
        return $this;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getFolders(): array
    {
        return $this->folders;
    }

    public function toArray(): array
    {
        return [
            ...$this->info->toArray(),
            'item' => [
                ...array_map(fn(Folder $folder) => $folder->toArray(), array_values($this->folders)),
                ...array_map(fn(Method $method) => $method->toArray(), $this->methods),
            ],
            'variables' => [
                ...array_map(fn(Variable $variable) => $variable->toArray(), array_values($this->variables)),
            ]
        ];
    }
}
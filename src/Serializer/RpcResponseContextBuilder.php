<?php

namespace Ufo\JsonRpcBundle\Serializer;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Ufo\RpcObject\RpcResponse;

use function array_map;
use function array_merge;
use function array_merge_recursive;
use function in_array;
use function is_array;

final class RpcResponseContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    protected string $parent = '';

    public function __construct() 
    {
        $this->withContext([RpcErrorNormalizer::RPC_CONTEXT => true]);
    }

    public function withContext(ContextBuilderInterface|array $context): static
    {
        if ($context instanceof ContextBuilderInterface) {
            $context = $context->toArray();
        }
        $this->context = array_merge_recursive($this->context, $context);
        return $this;
    }

    /**
     * @param string|string[] $group
     * @return $this
     */
    public function withGroup(string|array $group): static
    {
        if (is_array($group)) {
            array_map(
                function ($g) {
                    $this->withGroup($g);
                },
                $group
            );
        } else {
            $this->withContext([AbstractNormalizer::GROUPS => [$group]]);
        }

        return $this;
    }

    public function withResponseSignature(RpcResponse $response): static
    {
        $this->withGroup($this->parent = $response->getResponseSignature());
        return $this;
    }

    public function removeParent(): static
    {
        if (isset($this->context[AbstractNormalizer::GROUPS])) {
            $this->context[AbstractNormalizer::GROUPS] = array_filter(
                $this->context[AbstractNormalizer::GROUPS],
                function ($item)  {
                    return $item !== $this->parent;
                }
            );
        }
        return $this;
    }
}
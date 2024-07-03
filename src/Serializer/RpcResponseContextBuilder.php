<?php

namespace Ufo\JsonRpcBundle\Serializer;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Ufo\RpcObject\RpcResponse;

use function array_merge;
use function array_merge_recursive;

final class RpcResponseContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

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

    public function withResponseSignature(RpcResponse $response): static
    {
        $this->withContext([AbstractNormalizer::GROUPS => $response->getResponseSignature()]);
        return $this;
    }
}
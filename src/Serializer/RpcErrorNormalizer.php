<?php

namespace Ufo\JsonRpcBundle\Serializer;


use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Ufo\JsonRpcBundle\Exceptions\AbstractJsonRpcBundleException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Ufo\JsonRpcBundle\Exceptions\ExceptionToArrayTransformer;
use Ufo\JsonRpcBundle\Server\RpcErrorObject;

final class RpcErrorNormalizer implements NormalizerInterface
{
    const RPC_CONTEXT = 'rpc_handle';

    public function __construct(protected string $environment = 'dev')
    {
    }


    /**
     * @param \Throwable $object
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $this->environment = 'prod';
        $normalized = new ExceptionToArrayTransformer($object, $this->environment);

        return $normalized->infoByEnvirontment();
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof AbstractJsonRpcBundleException && ($context[static::RPC_CONTEXT] ?? false);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}


<?php

namespace Ufo\JsonRpcBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ExceptionToArrayTransformer;

final class RpcErrorNormalizer implements NormalizerInterface
{
    const RPC_CONTEXT = 'rpc_handle';

    public function __construct(protected string $environment = 'dev') {}

    /**
     * @param Throwable $object
     * @param ?string $format
     * @param array $context
     * @return array
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $normalized = new ExceptionToArrayTransformer($object, $this->environment);

        return $normalized->infoByEnvironment();
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof AbstractRpcErrorException && ($context[RpcErrorNormalizer::RPC_CONTEXT] ?? false);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AbstractRpcErrorException::class => true,
        ];
    }

}


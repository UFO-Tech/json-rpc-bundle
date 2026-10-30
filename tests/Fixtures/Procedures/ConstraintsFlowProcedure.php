<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\RpcObject\RPC;

/** Constraints на обов'язкових і необов'язкових параметрах для перевірки повного шляху валідації. */
#[RPC\Info('flow')]
class ConstraintsFlowProcedure implements IRpcService
{
    public function range(
        string $id,
        #[RPC\Assertions([new Assert\Range(min: 0, max: 8)])]
        int $scale = 2
    ): int {
        return $scale;
    }

    public function rangeBadDefault(
        string $id,
        #[RPC\Assertions([new Assert\Range(min: 0, max: 8)])]
        int $scale = 20
    ): int {
        return $scale;
    }

    public function optionalRange(
        string $id,
        #[RPC\Assertions([new Assert\Optional(), new Assert\Range(min: 0, max: 8)])]
        int $scale = 2
    ): int {
        return $scale;
    }

    public function nullableUuid(
        string $id,
        #[RPC\Assertions([new Assert\Uuid()])]
        ?string $token = null
    ): ?string {
        return $token;
    }

    public function notBlankNullDefault(
        string $id,
        #[RPC\Assertions([new Assert\NotBlank()])]
        ?string $name = null
    ): ?string {
        return $name;
    }

    public function required(
        #[RPC\Assertions([new Assert\NotBlank(), new Assert\Length(min: 3)])]
        string $name
    ): string {
        return $name;
    }

    public function variadic(string $id, string ...$tags): array
    {
        return [$id, $tags];
    }

    public function noParams(
        #[RPC\Assertions([new Assert\Range(min: 0, max: 8)])]
        int $scale = 20
    ): int {
        return $scale;
    }
}

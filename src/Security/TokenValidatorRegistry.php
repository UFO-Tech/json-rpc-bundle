<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Security;

use Psr\Container\ContainerInterface;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class TokenValidatorRegistry
{
    public function __construct(private ContainerInterface $validators) {}

    public function getValidator(string $group, ITokenValidator $fallback): ITokenValidator
    {
        return $this->validators->has($group) ? $this->validators->get($group) : $fallback;
    }
}

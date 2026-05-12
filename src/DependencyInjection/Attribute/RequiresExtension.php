<?php

namespace Ufo\JsonRpcBundle\DependencyInjection\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class RequiresExtension
{
    public const string DOCTRINE = 'doctrine';

    public function __construct(
        public string $extension,
    ) {}
}

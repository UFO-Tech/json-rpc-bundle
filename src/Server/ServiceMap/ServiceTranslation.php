<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Ufo\DTO\ArrayConstructibleTrait;
use Ufo\DTO\Interfaces\IArrayConstructible;
use Ufo\DTO\Interfaces\IArrayConvertible;
use Ufo\DTO\SmartArrayConvertibleTrait;

class ServiceTranslation implements IArrayConvertible, IArrayConstructible
{
    use SmartArrayConvertibleTrait, ArrayConstructibleTrait;

    public function __construct(
        readonly public string $locale,
        readonly public string $title,
        readonly public string $description,
    ) {}
}

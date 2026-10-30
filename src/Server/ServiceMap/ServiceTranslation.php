<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Ufo\DTO\ArrayConstructibleTrait;
use Ufo\DTO\Contract\ArrayConstructibleInterface;
use Ufo\DTO\Contract\ArrayConvertibleInterface;
use Ufo\DTO\SmartArrayConvertibleTrait;

class ServiceTranslation implements ArrayConvertibleInterface, ArrayConstructibleInterface
{
    use SmartArrayConvertibleTrait, ArrayConstructibleTrait;

    public function __construct(
        readonly public string $locale,
        readonly public string $title,
        readonly public string $description,
    ) {}
}

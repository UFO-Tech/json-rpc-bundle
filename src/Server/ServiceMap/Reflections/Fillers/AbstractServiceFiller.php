<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;

use function call_user_func;
use function count;
use function current;

#[AutoconfigureTag(IServiceFiller::TAG)]
abstract class AbstractServiceFiller implements IServiceFiller
{
    protected function typeFrom(ReflectionNamedType|string $type): string
    {
        return ($type instanceof ReflectionNamedType) ? $type->getName() : $type;
    }

    protected function getTypes(?ReflectionType $reflection): array|string
    {
        $return = TypeHintResolver::ANY->value;
        $returns = [];
        if ($reflection instanceof ReflectionNamedType) {
            $return = $reflection->getName();

            if ($return !== TypeHintResolver::MIXED->value && $reflection->allowsNull() && $return !== TypeHintResolver::NULL->value) {
                $returns[] = $return;
                $returns[] = TypeHintResolver::NULL->value;
            }
        } elseif ($reflection instanceof ReflectionUnionType) {
            foreach ($reflection->getTypes() as $type) {
                $returns[] = $this->getTypes($type);
            }
        }

        return !empty($returns) ? $returns : $return;
    }

    protected function getAttribute(
        ReflectionMethod $method,
        Service $service,
        string $attributeFQCN,
        ?string $serviceMethod = null
    ): ?object
    {
        $attr = $method->getAttributes($attributeFQCN);
        if (count($attr) > 0) {
            $obj = current($attr)->newInstance();
            if ($serviceMethod) {
                call_user_func([$service, $serviceMethod], $obj);
            }
            return $obj;
        }
        return null;
    }
}
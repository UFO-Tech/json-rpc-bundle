<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use DateTimeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Throwable;
use Ufo\DTO\Schema\Type\TypeHintResolver as T;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\DTO;

use function array_keys;
use function class_exists;
use function enum_exists;
use function get_object_vars;
use function is_a;
use function is_array;
use function is_string;
use function ltrim;
use function str_starts_with;

/** Збирає FQCN усіх DTO та enum, що потрапляють у документ API, із серверних структур сервісної мапи. */
class SchemaNamesCollector
{
    /** @var array<string, true> */
    protected array $found = [];

    public function __construct(
        protected ChainParamConvertor $paramConvertor,
    ) {}

    /**
     * @param iterable<Service> $services
     * @return string[]
     */
    public function collect(iterable $services): array
    {
        $this->found = [];
        foreach ($services as $service) {
            foreach ($service->getParams() as $param) {
                $this->fromSchema($param->getType());
            }
            $this->fromSchema($service->getReturn());
            foreach ($service->getParamsDto() as $reflectors) {
                foreach ($reflectors as $reflector) {
                    $this->fromDto($reflector->dto);
                }
            }
            if ($responseInfo = $service->getResponseInfo()) {
                $this->fromDto($responseInfo);
            }
            $this->fromAssertions($service->getAssertions());
        }

        return array_keys($this->found);
    }

    protected function fromSchema(array $schema): void
    {
        T::filterSchema($schema, function (array $node): void {
            $class = $node[T::CLASS_FQCN] ?? null;
            if (is_string($class)) {
                $this->addClass($class);
            }
        });
    }

    protected function addClass(string $class): void
    {
        $class = ltrim($class, '\\');
        if (isset($this->found[$class])) {
            return;
        }
        if (enum_exists($class)) {
            $this->found[$class] = true;
            return;
        }
        if (class_exists($class) && !is_a($class, DateTimeInterface::class, true)) {
            $this->fromDto(new DTO($class));
        }
    }

    protected function fromDto(DTO $dto): void
    {
        $class = ltrim($dto->dtoFQCN, '\\');
        if (isset($this->found[$class]) || !class_exists($class)) {
            return;
        }
        $this->found[$class] = true;

        try {
            new DtoReflector($dto, $this->paramConvertor);
            $format = $dto->getFormat();
        } catch (Throwable) {
            return;
        }

        foreach ($format['$collections'] ?? [] as $collection) {
            if ($collection instanceof DTO) {
                $this->fromDto($collection);
            }
        }
        $uses = $format['$uses'] ?? [];
        foreach ($format as $property => $type) {
            if (str_starts_with((string) $property, '$') || !is_string($type)) {
                continue;
            }
            try {
                $this->fromSchema(T::typeDescriptionToJsonSchema(ltrim($type, '?'), $uses));
            } catch (Throwable) {}
        }
    }

    protected function fromAssertions(?AssertionsCollection $collection): void
    {
        foreach ($collection?->getAssertionsCollection() ?? [] as $assertions) {
            foreach ($assertions?->assertions ?? [] as $constraint) {
                $this->fromConstraint($constraint);
            }
        }
    }

    protected function fromConstraint(mixed $constraint): void
    {
        if (is_array($constraint)) {
            foreach ($constraint as $item) {
                $this->fromConstraint($item);
            }
            return;
        }
        if (!$constraint instanceof Constraint) {
            return;
        }
        if ($constraint instanceof Choice && is_array($constraint->callback) && is_string($constraint->callback[0] ?? null)) {
            if (enum_exists($constraint->callback[0])) {
                $this->addClass($constraint->callback[0]);
            }
            return;
        }
        foreach (get_object_vars($constraint) as $value) {
            $this->fromConstraint($value);
        }
    }
}

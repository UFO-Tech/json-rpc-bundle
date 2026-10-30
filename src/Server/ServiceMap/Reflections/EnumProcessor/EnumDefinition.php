<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\EnumProcessor;

use Ufo\DTO\Contract\ArrayConstructibleInterface;
use Ufo\DTO\Contract\ArrayConvertibleInterface;
use Ufo\DTO\Schema\Enum\EnumResolver;
use Ufo\DTO\Schema\Type\TypeHintResolver;
use Ufo\DTO\Schema\Type\TypeHintResolver as T;

readonly class EnumDefinition implements ArrayConvertibleInterface, ArrayConstructibleInterface
{
    /**
     * @param string $name
     * @param string $type
     * @param array<string,string|int> $values
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $values,
    ) {}

    /**
     * @return array{
     *       type: string,
     *       x-ufo-enum: array{
     *           name: string,
     *           values: array<string|int>
     *       },
     *       enum: array<int, string|int>
     *   }
     */
    public function toArray(bool $publicOnly = true): array
    {
        return [
            TypeHintResolver::TYPE => $this->type,
            EnumResolver::ENUM => [
                EnumResolver::ENUM_NAME => $this->name,
                EnumResolver::METHOD_VALUES => $this->values,
            ],
            EnumResolver::ENUM_KEY => array_values($this->values)
        ];
    }

    public function getRef(): array
    {
        return [T::REF => '#/components/schemas/' . $this->name];
    }

    /**
     * @param array{
     *      type: string,
     *      x-ufo-enum: array{
     *          name: string,
     *          values: array<string|int>
     *      },
     *      enum: array<int, string|int>
     *  } $data
     * @param array $renameKey
     * @return static
     */
    public static function fromArray(array $data, array $renameKey = []): static
    {
        $enumName = $data[EnumResolver::ENUM][EnumResolver::ENUM_NAME];

        return new static(
            $enumName,
            $data['type'],
            $data[EnumResolver::ENUM][EnumResolver::METHOD_VALUES]
        );
    }

}

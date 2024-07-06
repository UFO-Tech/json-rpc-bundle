<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use function is_array;

enum TypeHintResolver: string
{
    case STRING = 'string';
    case STR = 'str';
    case ARR = 'arr';
    case ARRAY = 'array';
    case NULL = 'null';
    case NIL = 'nil';
    case OBJECT = 'object';
    case MIXED = 'mixed';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case ANY = 'any';
    case INTEGER = 'integer';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case VOID = 'void';
    case TRUE = 'true';
    case FALSE = 'false';
    case DBL = 'dbl';
    case DOUBLE = 'double';
    const string TYPE = 'type';

    public static function normalize(string $type): string
    {
        return match ($type) {
            self::ANY->value, self::MIXED->value => '',
            self::ARR->name, self::ARRAY->value => self::ARRAY->value,
            self::BOOL->value, self::TRUE->value, self::BOOLEAN->value, self::FALSE->value => self::BOOLEAN->value,
            self::DBL->value, self::DOUBLE->value, self::FLOAT->value => self::FLOAT->value,
            self::INTEGER->value, self::INT->value => self::INTEGER->value,
            self::NIL->value, self::NULL->value, self::VOID->value => self::NULL->value,
            self::STRING->value, self::STR->value => self::STRING->value,
            default => self::OBJECT->value
        };
    }

    public static function jsonSchemaToPhp(string $type): string
    {
        return match ($type) {
            self::NUMBER->value => self::FLOAT->value,
            self::INTEGER->value => self::INT->value,
            self::BOOLEAN->value => self::BOOL->value,
            default => $type
        };
    }

    public static function phpToJsonSchema(string|array $phpType): string|array
    {
        if (is_array($phpType)) {
            $types = [];
            foreach ($phpType as $type) {
                $types[] = [self::TYPE => self::phpToJsonSchema($type)];
            }
            return $types;
        }
        return match ($phpType) {
            self::MIXED->value => '',
            self::FLOAT->value => self::NUMBER->value,
            self::INT->value, => self::INTEGER->value,
            self::BOOL->value => self::BOOLEAN->value,
            default => $phpType
        };
    }

    public static function mixedForJsonSchema(): array
    {
        return [
            [self::TYPE => self::STRING->value],
            [self::TYPE => self::INTEGER->value],
            [self::TYPE => self::NUMBER->value],
            [self::TYPE => self::BOOLEAN->value],
            [self::TYPE => self::ARRAY->value],
            [self::TYPE => self::NULL->value],
        ];
    }

}

<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\ParamConvertors\IParamConvertor;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;
use Ufo\RpcObject\RPC\Param;

class ChainParamConvertorTest extends TestCase
{
    public function testToScalarUsesFirstSupportedConvertor(): void
    {
        $convertor = $this->buildChain([
            new class implements IParamConvertor {
                public function toScalar(object $object, array $context = [], ?callable $callback = null): array|string|int|float|null { return 'first'; }
                public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object { return new \stdClass(); }
                public function supported(string $classFQCN): bool { return true; }
                public function getParamAttr(string $classFQCN): Param { return new Param(Param::STRING); }
            },
        ]);

        $this->assertSame('first', $convertor->toScalar(new \stdClass()));
    }

    public function testToScalarThrowsWhenNoConvertorFound(): void
    {
        $convertor = $this->buildChain([]);

        $this->expectException(\RuntimeException::class);
        $convertor->toScalar(new \stdClass());
    }

    public function testToObjectUsesSupportedConvertorAndNeedConvertorFilter(): void
    {
        $target = new class {
            public string $value = 'ok';
        };

        $convertorClassA = new class implements IParamConvertor {
            public function toScalar(object $object, array $context = [], ?callable $callback = null): array|string|int|float|null { return 'a'; }
            public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object { return (object)['from' => 'a']; }
            public function supported(string $classFQCN): bool { return true; }
            public function getParamAttr(string $classFQCN): Param { return new Param(Param::STRING); }
        };
        $convertorClassB = new class($target) implements IParamConvertor {
            public function __construct(private object $target) {}
            public function toScalar(object $object, array $context = [], ?callable $callback = null): array|string|int|float|null { return 'b'; }
            public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object { return $this->target; }
            public function supported(string $classFQCN): bool { return true; }
            public function getParamAttr(string $classFQCN): Param { return new Param(Param::STRING); }
        };

        $chain = $this->buildChain([$convertorClassA, $convertorClassB]);

        $paramMeta = new \stdClass();
        $paramMeta->convertorFQCN = $convertorClassB::class;

        $result = $chain->toObject('v', [
            'classFQCN' => \stdClass::class,
            'param' => $paramMeta,
        ]);

        $this->assertSame($target, $result);
    }

    public function testToObjectThrowsWhenClassFqcnMissingOrNoConvertorMatches(): void
    {
        $chain = $this->buildChain([]);

        $this->expectException(\RuntimeException::class);
        $chain->toObject('v', []);
    }

    public function testSupportedAndGetParamAttr(): void
    {
        $param = new Param(Param::INT);
        $convertor = $this->buildChain([
            new class($param) implements IParamConvertor {
                public function __construct(private Param $param) {}
                public function toScalar(object $object, array $context = [], ?callable $callback = null): array|string|int|float|null { return 1; }
                public function toObject(int|string|float|null $value, array $context = [], ?callable $callback = null): ?object { return new \stdClass(); }
                public function supported(string $classFQCN): bool { return $classFQCN === \stdClass::class; }
                public function getParamAttr(string $classFQCN): Param { return $this->param; }
            },
        ]);

        $this->assertTrue($convertor->supported(\stdClass::class));
        $this->assertFalse($convertor->supported(\DateTime::class));
        $this->assertSame($param, $convertor->getParamAttr(\stdClass::class));
        $this->assertNull($convertor->getParamAttr(\DateTime::class));
    }

    /**
     * @param IParamConvertor[] $convertors
     */
    private function buildChain(array $convertors): ChainParamConvertor
    {
        return new ChainParamConvertor(
            new JsonSchemaPropertyNormalizer(new Generator([])),
            $convertors
        );
    }
}


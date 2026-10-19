<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\ParamConvertors;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Ufo\JsonRpcBundle\ParamConvertors\DoctrineEntityConverter;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\RpcObject\RPC\Param;

class DoctrineEntityConverterTest extends TestCase
{
    public function testGetParamAttrUsesIdentifierType(): void
    {
        $param = $this->buildConverter('int')->getParamAttr(stdClass::class);

        $this->assertSame('int', $param->getType());
        $this->assertSame(DoctrineEntityConverter::class, $param->context[Param::C_CONVERTOR] ?? null);
    }

    #[DataProvider('nullableParamAttrProvider')]
    public function testGetNullableParamAttrAddsNullOnlyForNullableParam(string $idType, array|string $realType, array|string $expected): void
    {
        $param = $this->buildConverter($idType)->getNullableParamAttr(stdClass::class, new ParamDefinition('entity', [], $realType));

        $this->assertSame($expected, $param->getType());
        $this->assertSame(DoctrineEntityConverter::class, $param->context[Param::C_CONVERTOR] ?? null);
    }

    public static function nullableParamAttrProvider(): iterable
    {
        yield 'nullable int id' => ['int', ['null', stdClass::class], ['int', 'null']];
        yield 'required int id' => ['int', stdClass::class, 'int'];
        yield 'nullable string id' => ['string', '?' . stdClass::class, ['string', 'null']];
        yield 'required string id' => ['string', stdClass::class, 'string'];
    }

    private function buildConverter(string $idType): DoctrineEntityConverter
    {
        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getIdentifierFieldNames')->willReturn(['id']);
        $meta->method('getTypeOfField')->with('id')->willReturn($idType);

        $em = $this->createMock(ObjectManager::class);
        $em->method('getClassMetadata')->willReturn($meta);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return new DoctrineEntityConverter($registry);
    }
}

<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\ServiceMap;

use PHPUnit\Framework\TestCase;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Factory\DefaultDTOTransformerFactory;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceTranslation;
use Ufo\RpcObject\RPC\AssertionsCollection;
use Ufo\RpcObject\RPC\Info;

class ServiceTest extends TestCase
{
    public function testConstructorBuildsMethodAndProcedureNames(): void
    {
        $service = $this->createService('main.ping');

        $this->assertSame('ping', $service->getMethodName());
        $this->assertSame('main', $service->procedure);
        $this->assertSame('main.ping', $service->name);
        $this->assertSame('App\\Rpc\\DummyProcedure', $service->getProcedureFQCN());
    }

    public function testDescriptionAndThrowsAndDeprecatedFlags(): void
    {
        $service = $this->createService('main.ping');

        $service->setDescription('Ping method');
        $service->setThrows(['RuntimeException']);
        $service->addThrow('LogicException');
        $service->setDeprecated();

        $this->assertSame('Ping method', $service->getDescription());
        $this->assertSame(['RuntimeException', 'LogicException'], $service->getThrows());
        $this->assertTrue($service->isDeprecated());
    }

    public function testAddParamAndDefaultParams(): void
    {
        $service = $this->createService('main.ping');
        $required = new ParamDefinition('id', ['integer'], 'int');
        $optional = (new ParamDefinition('limit', ['integer'], 'int'))->setDefault(10);

        $service->addParam($required)->addParam($optional);

        $this->assertCount(2, $service->getParams());
        $this->assertSame(['foo' => 'bar', 'limit' => 10], $service->getDefaultParams(['foo' => 'bar']));
    }

    public function testSetReturnWithTypesAndDocType(): void
    {
        $service = $this->createService('main.ping');

        $service->setReturn('string');
        $this->assertSame(['type' => 'string'], $service->getReturn());

        $service->setReturn('mixed', 'int', 'count');
        $this->assertSame(['type' => 'integer'], $service->getReturn());
        $this->assertSame('count', $service->getReturnDescription());
    }

    public function testSchemaAssertionsAndUfoAssertions(): void
    {
        $service = $this->createService('main.ping');
        $assertions = new AssertionsCollection();

        $service->setSchema(['a' => 1])->setSchema(['b' => 2]);
        $service->setAssertions($assertions);
        $service->setUfoAssertions('id', 'required');

        $this->assertSame(['a' => 1, 'b' => 2], $service->getSchema());
        $this->assertSame($assertions, $service->getAssertions());
        $this->assertSame('required', $service->getUfoAssertion('id'));
        $this->assertSame(['id' => 'required'], $service->getUfoAssertions());
    }

    public function testAttributeCollectionAndToJsonAndToString(): void
    {
        $factory = DefaultDTOTransformerFactory::default(); // DTOTransformerFactoryInterface
        DTOTransformer::boot($factory->create());

        $service = $this->createService('main.ping');
        $attribute = new \stdClass();

        $service->setAttribute($attribute);

        $this->assertSame($attribute, $service->getAttrCollection()->getAttribute(\stdClass::class));
        $this->assertIsString($service->toJson());
        $this->assertIsString((string)$service);
    }

    public function testValidateParamTypeForStringAndArray(): void
    {
        $this->assertSame(['type' => 'string'], Service::validateParamType('string'));
        $this->assertSame([
            ['type' => 'integer'],
            ['type' => 'string'],
        ], Service::validateParamType(['int', 'string']));
    }

    public function testParamsDtoAndResponseInfoDefaults(): void
    {
        $service = $this->createService('main.ping');

        $this->assertSame([], $service->getParamsDto());
        $this->assertNull($service->getResponseInfo());
        $this->assertNull($service->getReturnItems());
    }

    public function testTitleAndDescriptionFallBackToServiceDefaultsWithoutTranslations(): void
    {
        $service = $this->createService('main.ping');
        $service->setDescription('Ping method');
        $service->setTitle('Ping');

        $this->assertSame('Ping', $service->getTitle());
        $this->assertSame('Ping method', $service->getDescription());

        $this->assertSame('Ping', $service->getTitle('uk'));
        $this->assertSame('Ping method', $service->getDescription('uk'));
    }

    public function testKnownLocaleReturnsItsOwnTranslation(): void
    {
        $service = $this->createService('main.ping');

        $service->addTranslation(new ServiceTranslation('uk', title: 'Пінг', description: 'Метод пінгу'));
        $service->addTranslation(new ServiceTranslation('fr', title: 'Ping FR', description: 'Methode ping'));

        $this->assertSame('Пінг', $service->getTitle('uk'));
        $this->assertSame('Метод пінгу', $service->getDescription('uk'));

        $this->assertSame('Ping FR', $service->getTitle('fr'));
        $this->assertSame('Methode ping', $service->getDescription('fr'));
    }

    public function testUnknownLocaleFallsBackToFirstAddedTranslation(): void
    {
        $service = $this->createService('main.ping');

        $service->addTranslation(new ServiceTranslation('uk', title: 'Пінг', description: 'Метод пінгу'));
        $service->addTranslation(new ServiceTranslation('fr', title: 'Ping FR', description: 'Methode ping'));

        $this->assertSame('Пінг', $service->getTitle('de'));
        $this->assertSame('Метод пінгу', $service->getDescription('de'));
    }

    public function testAddTranslationOverwritesExistingLocale(): void
    {
        $service = $this->createService('main.ping');
        $service->addTranslation(new ServiceTranslation('uk', title: 'Пінг', description: 'Старий опис'));
        $service->addTranslation(new ServiceTranslation('uk', title: 'Пінг2', description: 'Новий опис'));

        $this->assertSame('Пінг2', $service->getTitle('uk'));
        $this->assertSame('Новий опис', $service->getDescription('uk'));
    }

    public function testTranslationsRoundTripThroughArray(): void
    {
        \Ufo\DTO\TransformerBootstrapper::boot();

        $service = $this->createService('main.ping');
        $service->setDescription('Ping method');
        $service->addTranslation(new ServiceTranslation('uk', title: 'Пінг', description: 'Метод пінгу'));

        $restored = Service::fromArray($service->toArray(false));

        $this->assertSame('Пінг', $restored->getTitle('uk'));
        $this->assertSame('Метод пінгу', $restored->getDescription('uk'));
        $this->assertSame('Ping method', $restored->getDescription());
    }

    private function createService(string $name): Service
    {
        return new Service($name, 'App\\Rpc\\DummyProcedure', new Info('main'));
    }
}


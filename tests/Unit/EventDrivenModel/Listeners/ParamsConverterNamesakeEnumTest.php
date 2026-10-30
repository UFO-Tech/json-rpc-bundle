<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Ufo\DTO\Schema\Enum\EnumResolver;
use Ufo\DTO\Schema\Type\TypeHintResolver;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\ParamsConverterEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\ParamConvertors\BackedEnumConvertor;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\ListenerFixtureProcedure;
use Ufo\JsonRpcBundle\Tests\Unit\MockBackedEnum;
use Ufo\JsonRpcBundle\Tests\Unit\Namesake\MockBackedEnum as NamesakeEnum;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RPC\Param;
use Ufo\RpcObject\RpcRequest;

/** Enum-параметр розвʼязується за classFQCN схеми, а не за короткою назвою з use-мапи. */
class ParamsConverterNamesakeEnumTest extends TestCase
{
    /** @var RpcErrorEvent[] */
    private array $errorEvents = [];

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->errorEvents = [];
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(RpcEvent::ERROR, function (RpcErrorEvent $event): void {
            $this->errorEvents[] = $event;
        });
    }

    public function testNamesakeEnumUsesClassFromSchema(): void
    {
        $event = $this->event(['paint' => 'x'], NamesakeEnum::class);

        $this->listener()->dataToDTOTransform($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertSame(NamesakeEnum::X, $event->params['paint']);
    }

    public function testValueOfOtherNamesakeIsRejected(): void
    {
        $event = $this->event(['paint' => 'a'], NamesakeEnum::class);

        $this->listener()->dataToDTOTransform($event);

        $this->assertCount(1, $this->errorEvents);
        $exception = $this->errorEvents[0]->exception;
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame(['paint' => ['Invalid value "a", expected one of: x, y']], $exception->getConstraintsImposed());
    }

    public function testNamesakeEnumCollectionUsesClassFromSchema(): void
    {
        $event = $this->event(['paint' => ['x', 'y']], NamesakeEnum::class, collection: true);

        $this->listener()->dataToDTOTransform($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertSame([NamesakeEnum::X, NamesakeEnum::Y], $event->params['paint']);
    }

    public function testSchemaWithoutClassFallsBackToUses(): void
    {
        $event = $this->event(['paint' => 'a'], null);

        $this->listener()->dataToDTOTransform($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertSame(MockBackedEnum::A, $event->params['paint']);
    }

    private function listener(): ParamsConverterEventListener
    {
        return new ParamsConverterEventListener(
            new ChainParamConvertor(new JsonSchemaPropertyNormalizer(new Generator([])), [new BackedEnumConvertor()]),
            new RpcEventFactory($this->dispatcher)
        );
    }

    /**
     * Use-мапа класу веде коротку назву «MockBackedEnum» на інший enum, ніж тип параметра.
     *
     * @param array<string, mixed> $params
     */
    private function event(array $params, ?string $classFQCN, bool $collection = false): RpcPreExecuteEvent
    {
        $enumSchema = [
            TypeHintResolver::TYPE => TypeHintResolver::STRING->value,
            EnumResolver::ENUM => [EnumResolver::ENUM_NAME => 'MockBackedEnum', EnumResolver::METHOD_VALUES => []],
        ];
        if (null !== $classFQCN) {
            $enumSchema[TypeHintResolver::CLASS_FQCN] = $classFQCN;
        }
        $schema = $collection
            ? [TypeHintResolver::TYPE => TypeHintResolver::ARRAY->value, TypeHintResolver::ITEMS => $enumSchema]
            : $enumSchema;

        $param = new ParamDefinition('paint', $schema, $collection ? TypeHintResolver::ARRAY->value : ($classFQCN ?? MockBackedEnum::class));
        $param->attributesCollection->addAttribute(new Param(
            Param::STRING,
            collection: $collection,
            context: [Param::C_CONVERTOR => BackedEnumConvertor::class],
        ));

        $service = new Service(
            'main.paint',
            ListenerFixtureProcedure::class,
            new Info('main'),
            uses: ['MockBackedEnum' => MockBackedEnum::class],
        );
        $service->addParam($param);

        return new RpcPreExecuteEvent(new RpcRequest(1, 'main.paint'), $service, $params);
    }
}

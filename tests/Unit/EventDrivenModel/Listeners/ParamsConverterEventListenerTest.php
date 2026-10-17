<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Ufo\DTO\DTOTransformer;
use Ufo\DTO\Factory\DefaultDTOTransformerFactory;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreResponseEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\ParamsConverterEventListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\DtoReflector;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\OrderDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\PersonDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\ProductDto;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\ListenerFixtureProcedure;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;
use Ufo\JsonRpcBundle\Validations\JsonSchema\JsonSchemaPropertyNormalizer;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RpcResponse;

class ParamsConverterEventListenerTest extends TestCase
{
    /**
     * @var RpcErrorEvent[]
     */
    private array $errorEvents = [];

    private EventDispatcher $dispatcher;

    private ChainParamConvertor $paramConvertor;

    private bool $transformerBooted = false;

    protected function setUp(): void
    {
        if (!DTOTransformer::isInitialized()) {
            DTOTransformer::boot(DefaultDTOTransformerFactory::default()->create());
            $this->transformerBooted = true;
        }

        $this->errorEvents = [];
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(RpcEvent::ERROR, function (RpcErrorEvent $event): void {
            $this->errorEvents[] = $event;
        });
        $this->paramConvertor = new ChainParamConvertor(new JsonSchemaPropertyNormalizer(new Generator([])));
    }

    protected function tearDown(): void
    {
        if ($this->transformerBooted) {
            DTOTransformer::reset();
        }
    }

    public function testSingleDtoIsTransformedWithoutError(): void
    {
        $event = $this->createEvent(['person' => ['name' => 'bob', 'age' => 30]], [
            'person' => [new DTO(PersonDto::class)],
        ]);

        $this->createListener()->dataToDTOTransform($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertInstanceOf(PersonDto::class, $event->params['person']);
        $this->assertSame('bob', $event->params['person']->name);
    }

    public function testSingleDtoFailureIsReportedWithRequestedMethodInMessage(): void
    {
        $event = $this->createEvent(['person' => ['name' => 'bob']], [
            'person' => [new DTO(PersonDto::class)],
        ]);

        $this->createListener()->dataToDTOTransform($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame('Invalid Data for call method: main.expectDto', $exception->getMessage());
        $this->assertSame(
            ['person' => ["Missing required key for property: 'age'"]],
            $exception->getConstraintsImposed()
        );
    }

    public function testNestedDtoFailureMessageIsStrippedOfNamespaces(): void
    {
        $event = $this->createEvent(['person' => ['customer' => 'oops']], [
            'person' => [new DTO(OrderDto::class)],
        ]);

        $this->createListener()->dataToDTOTransform($event);

        $exception = $this->singleFiredException();
        $this->assertSame(
            [
                'person' => [
                    'Failed to resolve __construct() parameter $customer: Cannot assign string to DTO PersonDto',
                ],
            ],
            $exception->getConstraintsImposed()
        );
    }

    public function testCollectionItemsMatchedByDifferentCandidatesProduceNoError(): void
    {
        $event = $this->createEvent(
            ['person' => [['name' => 'bob', 'age' => 30], ['sku' => 'A-1']]],
            ['person' => [new DTO(PersonDto::class, collection: true), new DTO(ProductDto::class, collection: true)]]
        );

        $this->createListener()->dataToDTOTransform($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertInstanceOf(PersonDto::class, $event->params['person'][0]);
        $this->assertInstanceOf(ProductDto::class, $event->params['person'][1]);
    }

    public function testCollectionItemRejectedByEveryCandidateIsReportedUnderItsOwnKey(): void
    {
        $event = $this->createEvent(
            ['person' => [['name' => 'bob', 'age' => 30], ['unknown' => 1]]],
            ['person' => [new DTO(PersonDto::class, collection: true), new DTO(ProductDto::class, collection: true)]]
        );

        $this->createListener()->dataToDTOTransform($event);

        $exception = $this->singleFiredException();
        $this->assertSame(
            [
                'person' => [
                    1 => [
                        "Missing required key for property: 'name'",
                        "Missing required key for property: 'sku'",
                    ],
                ],
            ],
            $exception->getConstraintsImposed()
        );
    }

    public function testRuntimeResponseFormatIsBuiltOncePerResultClass(): void
    {
        $listener = $this->createListener();
        $service = new Service('main.order', ListenerFixtureProcedure::class, new Info('main'));

        $first = $this->objectToScalar($listener, $service, new OrderDto(new PersonDto('Ann', 30)));
        $cache = (new \ReflectionProperty($listener, 'runtimeResponseInfo'))->getValue($listener);
        $second = $this->objectToScalar($listener, $service, new OrderDto(new PersonDto('Bob', 40)));
        $cacheAfter = (new \ReflectionProperty($listener, 'runtimeResponseInfo'))->getValue($listener);

        $this->assertSame(['name' => 'Ann', 'age' => 30], $first['customer']);
        $this->assertSame(['name' => 'Bob', 'age' => 40], $second['customer']);
        $this->assertSame([OrderDto::class], array_keys($cacheAfter));
        $this->assertSame($cache[OrderDto::class], $cacheAfter[OrderDto::class]);
    }

    public function testResponseInfoFromServiceMapIsUsedWithoutRuntimeReflection(): void
    {
        $listener = $this->createListener();
        $service = new Service('main.order', ListenerFixtureProcedure::class, new Info('main'));
        $responseInfo = new DTO(OrderDto::class);
        new DtoReflector($responseInfo, $this->paramConvertor);
        $service->setResponseInfo($responseInfo);

        $result = $this->objectToScalar($listener, $service, new OrderDto(new PersonDto('Ann', 30)));

        $this->assertSame(['name' => 'Ann', 'age' => 30], $result['customer']);
        $this->assertSame([], (new \ReflectionProperty($listener, 'runtimeResponseInfo'))->getValue($listener));
    }

    public function testResponseFormatIsReusedFromMetadataCacheByNextRequest(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\PhpFilesAdapter('', 0, sys_get_temp_dir().'/ufo-rpc-meta-'.bin2hex(random_bytes(6)));
        $service = new Service('main.order', ListenerFixtureProcedure::class, new Info('main'));
        $builds = 0;

        // Two listener instances = two FPM requests sharing cache.system.
        $first = $this->objectToScalar($this->countingListener($builds, $pool), $service, new OrderDto(new PersonDto('Ann', 30)));
        $second = $this->objectToScalar($this->countingListener($builds, $pool), $service, new OrderDto(new PersonDto('Bob', 40)));

        $this->assertSame(1, $builds);
        $this->assertSame(['name' => 'Ann', 'age' => 30], $first['customer']);
        $this->assertSame(['name' => 'Bob', 'age' => 40], $second['customer']);
        $pool->clear();
    }

    public function testContainerRebuildInvalidatesMetadataCache(): void
    {
        $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $service = new Service('main.order', ListenerFixtureProcedure::class, new Info('main'));
        $builds = 0;

        $this->objectToScalar($this->countingListener($builds, $pool, 'build-1'), $service, new OrderDto(new PersonDto('Ann', 30)));
        $this->objectToScalar($this->countingListener($builds, $pool, 'build-2'), $service, new OrderDto(new PersonDto('Ann', 30)));

        $this->assertSame(2, $builds);
    }

    public function testMetadataCacheIsNotUsedInDebug(): void
    {
        $pool = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItem');
        $service = new Service('main.order', ListenerFixtureProcedure::class, new Info('main'));
        $builds = 0;

        $result = $this->objectToScalar($this->countingListener($builds, $pool, debug: true), $service, new OrderDto(new PersonDto('Ann', 30)));

        $this->assertSame(1, $builds);
        $this->assertSame(['name' => 'Ann', 'age' => 30], $result['customer']);
    }

    private function countingListener(int &$builds, \Psr\Cache\CacheItemPoolInterface $pool, string $buildId = 'build-1', bool $debug = false): ParamsConverterEventListener
    {
        $metadataCache = new \Ufo\JsonRpcBundle\Server\RpcCache\RpcMetadataCache($pool, $buildId, $debug);
        return new class($builds, $this->paramConvertor, new RpcEventFactory($this->dispatcher), $metadataCache) extends ParamsConverterEventListener {
            private int $builds;

            public function __construct(int &$builds, ...$args)
            {
                $this->builds = &$builds;
                parent::__construct(...$args);
            }

            protected function buildResponseInfo(string $resultClass): DTO
            {
                $this->builds++;
                return parent::buildResponseInfo($resultClass);
            }
        };
    }

    private function objectToScalar(ParamsConverterEventListener $listener, Service $service, object $result): array
    {
        $request = new RpcRequest(1, $service->name);
        $request->setResponse(new RpcResponse(1, $result, requestObject: $request));
        $listener->objectToScalar(new RpcPreResponseEvent($request->getResponseObject(), $request, $service));

        return $request->getResponseObject()->getResult();
    }

    private function singleFiredException(): \Throwable
    {
        $this->assertCount(1, $this->errorEvents);

        return $this->errorEvents[0]->exception;
    }

    private function createListener(): ParamsConverterEventListener
    {
        return new ParamsConverterEventListener(
            $this->paramConvertor,
            new RpcEventFactory($this->dispatcher)
        );
    }

    /**
     * @param array<string,DTO[]> $paramsDto
     */
    private function createEvent(array $params, array $paramsDto): RpcPreExecuteEvent
    {
        $service = new Service('main.expectDto', ListenerFixtureProcedure::class, new Info('main'));
        foreach ($paramsDto as $paramName => $dtoList) {
            foreach ($dtoList as $dto) {
                $service->addParamsDto($paramName, new DtoReflector($dto, $this->paramConvertor));
            }
        }

        return new RpcPreExecuteEvent(new RpcRequest(1, 'main.expectDto'), $service, $params);
    }
}

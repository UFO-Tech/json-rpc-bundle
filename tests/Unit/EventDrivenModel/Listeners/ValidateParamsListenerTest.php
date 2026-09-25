<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\EventDrivenModel\Listeners;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcErrorEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Events\RpcPreExecuteEvent;
use Ufo\JsonRpcBundle\EventDrivenModel\Listeners\ValidateParamsListener;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\ListenerFixtureProcedure;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcObject\RPC\Info;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\Rules\Validator\RpcValidator;

class ValidateParamsListenerTest extends TestCase
{
    /**
     * @var RpcErrorEvent[]
     */
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

    public static function messagesProvider(): array
    {
        return [
            'dto fqcn' => [
                'Cannot assign string to DTO App\\Dto\\PersonDto',
                'Cannot assign string to DTO PersonDto',
            ],
            'type hint fqcn' => [
                'Parameter "id" must be of type App\\Model\\Identifier, string given',
                'Parameter "id" must be of type Identifier, string given',
            ],
            'several fqcn in one message' => [
                'Expected App\\Dto\\PersonDto, got Vendor\\Lib\\Deep\\ProductDto',
                'Expected PersonDto, got ProductDto',
            ],
            'nothing to normalize' => [
                "Missing required key for property: 'age'",
                "Missing required key for property: 'age'",
            ],
        ];
    }

    #[DataProvider('messagesProvider')]
    public function testNormalizeMessageStripsNamespaces(string $message, string $expected): void
    {
        $this->assertSame($expected, ValidateParamsListener::normalizeMessage($message));
    }

    public function testConstraintValidationRewritesMessageWithRequestedMethod(): void
    {
        $constraints = ['name' => ['This value is too short.']];
        $validator = $this->createMock(RpcValidator::class);
        $validator->method('validateMethodParams')
            ->willThrowException(new ConstraintsImposedException('Invalid Data for call method: ping', $constraints));

        $event = $this->createEvent('ping', ['name' => 'a']);
        $this->createListener($validator)->constraintValidation($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame('Invalid Data for call method: main.ping', $exception->getMessage());
        $this->assertSame($constraints, $exception->getConstraintsImposed());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testConstraintValidationKeepsSilentOnValidParams(): void
    {
        $event = $this->createEvent('ping', ['name' => 'bob']);
        $this->createListener()->constraintValidation($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testConstraintValidationSkipsRequestWithError(): void
    {
        $validator = $this->createMock(RpcValidator::class);
        $validator->expects($this->never())->method('validateMethodParams');

        $event = $this->createEvent('ping', ['name' => 'bob']);
        $event->rpcRequest->setError(new \RuntimeException('already broken'));

        $this->createListener($validator)->constraintValidation($event);

        $this->assertSame([], $this->errorEvents);
    }

    public function testNamedParamsCollectEveryMissingRequiredParamIntoOneError(): void
    {
        $event = $this->createEvent('triple', ['a' => 'x'], ['a', 'b', 'c']);

        $this->createListener()->validateAndPrepareNamedParams($event);

        $exception = $this->singleFiredException();
        $this->assertInstanceOf(ConstraintsImposedException::class, $exception);
        $this->assertSame('Invalid Data for call method: main.triple', $exception->getMessage());
        $this->assertSame([
            'b' => 'Required parameter "b" not passed',
            'c' => 'Required parameter "c" not passed',
        ], $exception->getConstraintsImposed());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testNamedParamsFillDefaultsAndLocatorArgumentsWithoutError(): void
    {
        $dependency = new \stdClass();
        $event = $this->createEvent('injected', ['name' => 'bob'], ['name', 'dependency', 'times']);

        $this->createListener(argumentLocator: new ServiceLocator([
            'dependency' => static fn (): \stdClass => $dependency,
        ]))->validateAndPrepareNamedParams($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertFalse($event->isPropagationStopped());
        $this->assertSame(['name' => 'bob', 'dependency' => $dependency, 'times' => 5], $event->params);
    }

    public function testNamedParamsDoNotReportOptionalParamsAsMissing(): void
    {
        $event = $this->createEvent('ping', ['name' => 'bob'], ['name', 'times']);

        $this->createListener()->validateAndPrepareNamedParams($event);

        $this->assertSame([], $this->errorEvents);
        $this->assertSame(['name' => 'bob', 'times' => 1], $event->params);
    }

    private function singleFiredException(): \Throwable
    {
        $this->assertCount(1, $this->errorEvents);

        return $this->errorEvents[0]->exception;
    }

    private function createListener(
        ?RpcValidator $validator = null,
        ?ServiceLocator $argumentLocator = null,
    ): ValidateParamsListener {
        return new ValidateParamsListener(
            new RpcEventFactory($this->dispatcher),
            $validator ?? $this->createMock(RpcValidator::class),
            new ServiceLocator([
                ListenerFixtureProcedure::class => static fn (): ListenerFixtureProcedure => new ListenerFixtureProcedure(),
            ]),
            new ServiceLocator([
                ListenerFixtureProcedure::class . '::ping' => static fn (): ServiceLocator => $argumentLocator ?? new ServiceLocator([]),
                ListenerFixtureProcedure::class . '::triple' => static fn (): ServiceLocator => $argumentLocator ?? new ServiceLocator([]),
                ListenerFixtureProcedure::class . '::injected' => static fn (): ServiceLocator => $argumentLocator ?? new ServiceLocator([]),
            ]),
        );
    }

    private function createEvent(string $method, array $params, array $declaredParams = []): RpcPreExecuteEvent
    {
        $service = new Service('main.' . $method, ListenerFixtureProcedure::class, new Info('main'));
        foreach ($declaredParams as $name) {
            $service->addParam(new ParamDefinition($name, ['string'], 'string'));
        }

        return new RpcPreExecuteEvent(new RpcRequest(1, 'main.' . $method, $params), $service, $params);
    }
}

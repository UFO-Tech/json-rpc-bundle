<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\ApiMethod\PingProcedure;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\JsonRpcBundle\Server\RpcServer;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;
use Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Generator;

/**
 * Юніт-тести бандла не піднімають ядро, тож DI-шар (Extension, Configuration,
 * компайлер-паси, автоконфігурація тегів) не мав жодного покриття. Саме тут
 * Symfony ламає найболючіше при мажорних оновленнях.
 */
class ContainerCompilesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        TestKernel::$rpcConfig = [];
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestKernel::$rpcConfig = [];
        restore_exception_handler();
    }

    private function containerWith(array $config = []): ContainerInterface
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = $config;
        self::bootKernel();

        return self::getContainer();
    }

    public function testContainerCompilesWithTheBundleRegistered(): void
    {
        $this->assertTrue($this->containerWith()->has('kernel'));
    }

    public function testCoreServerServicesAreWired(): void
    {
        $container = $this->containerWith();

        $this->assertInstanceOf(RpcServer::class, $container->get(RpcServer::class));
        $this->assertInstanceOf(RpcRequestHandler::class, $container->get(RpcRequestHandler::class));
        $this->assertInstanceOf(ApiController::class, $container->get(ApiController::class));
    }

    public function testBuiltInPingProcedureIsRegisteredAsRpcService(): void
    {
        $container = $this->containerWith();

        $ping = $container->get(PingProcedure::class);
        $this->assertInstanceOf(IRpcService::class, $ping);
        $this->assertSame('PONG', $ping->ping());
    }

    public function testConstraintGeneratorsAreCollectedByTag(): void
    {
        $container = $this->containerWith();

        // Generator отримує генератори через #[AutowireIterator] — якщо тег або
        // автоконфігурація зламані, ітератор приїде порожній.
        $this->assertInstanceOf(Generator::class, $container->get(Generator::class));
    }

    public function testConfigTreeIsFlattenedIntoDottedParameters(): void
    {
        $container = $this->containerWith([
            'cache' => ['ttl' => 120, 'prefix' => 'my_prefix'],
            'security' => ['protected_api' => false, 'protected_doc' => false],
            'docs' => ['project_name' => 'my-project', 'validations' => []],
        ]);

        // services.yaml посилається на %ufo_json_rpc.cache.prefix% — якщо mapTreeToParams
        // зламається, контейнер не збереться взагалі.
        $this->assertTrue($container->hasParameter('ufo_json_rpc'));
        $this->assertSame('my_prefix', $container->getParameter('ufo_json_rpc.cache.prefix'));
        $this->assertSame(120, $container->getParameter('ufo_json_rpc.cache.ttl'));
        $this->assertSame('my-project', $container->getParameter('ufo_json_rpc.docs.project_name'));
    }

    public function testCacheDefaultsAreAppliedWhenSectionIsEmpty(): void
    {
        $container = $this->containerWith([
            'cache' => [],
            'security' => ['protected_api' => false, 'protected_doc' => false],
            'docs' => ['validations' => []],
        ]);

        $this->assertSame('rpc_cache', $container->getParameter('ufo_json_rpc.cache.prefix'));
    }

    public function testSecurityFlagsReachTheContainer(): void
    {
        $container = $this->containerWith([
            'cache' => [],
            'security' => ['protected_api' => true, 'protected_doc' => true, 'tokens' => ['abc']],
            'docs' => ['validations' => []],
        ]);

        $this->assertTrue($container->getParameter('ufo_json_rpc.security.protected_api'));
        $this->assertSame(['abc'], $container->getParameter('ufo_json_rpc.security.tokens'));
    }
}

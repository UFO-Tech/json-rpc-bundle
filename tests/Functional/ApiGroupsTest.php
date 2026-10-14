<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Controller\ApiController;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\JsonRpcBundle\Server\ServiceMap\ServiceMap;
use Ufo\JsonRpcBundle\Tests\Fixtures\GroupedKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\GroupTokenValidator;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;

class ApiGroupsTest extends KernelTestCase
{
    protected static function getKernelClass(): string { return GroupedKernel::class; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestKernel::$rpcConfig = [];
        restore_exception_handler();
    }

    private function request(string $path, string $method = 'GET', ?string $rpcMethod = null, ?string $token = null): array
    {
        $request = Request::create('http://localhost'.$path, $method, server: $token ? ['HTTP_UFO_RPC_TOKEN' => $token] : [], content: $rpcMethod ? json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $rpcMethod]) : null);
        $response = self::$kernel->handle($request);
        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }

    public function testImportsKeepAllRoutesAndLegacyAliases(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        foreach (['general' => '/rpc', 'bfa' => '/rpc/bfa', 'bff' => '/rpc/bff'] as $group => $path) {
            self::assertSame($path, $router->generate($group.'_'.ApiController::API_ROUTE));
            self::assertSame($path.'/v2/postman', $router->generate($group.'_'.ApiController::POSTMAN_ROUTE_VER, ['ver' => 'v2']));
            self::assertSame($group, $router->match($path)['api_group']);
        }
        self::assertSame('/rpc', $router->generate(ApiController::API_ROUTE));
    }

    public function testMapsUseMethodOverridesAndIgnoreApi(): void
    {
        self::bootKernel();
        $map = self::getContainer()->get(ServiceMap::class);
        $context = self::getContainer()->get(ApiGroupContext::class);
        $expected = [
            'general' => ['area.shared', 'area.generalOnly', 'ping'],
            'bfa' => ['area.shared', 'area.privateMethod'],
            'bff' => ['area.shared'],
        ];
        foreach ($expected as $group => $methods) {
            $context->setGroup($group);
            $actual = array_keys($map->getServices());
            sort($actual);
            sort($methods);
            self::assertSame($methods, $actual);
        }
    }

    public function testHttpExecutionUsesGroupAndDoesNotLeakBetweenRequests(): void
    {
        self::bootKernel();
        self::assertSame('bfa', $this->request('/rpc/bfa', 'POST', 'area.privateMethod')[1]['result']);
        self::assertSame(-32601, $this->request('/rpc/bff', 'POST', 'area.privateMethod')[1]['error']['code']);
        self::assertSame('PONG', $this->request('/rpc', 'POST', 'ping')[1]['result']);
        self::assertSame(-32601, $this->request('/rpc/bfa', 'POST', 'area.ignored')[1]['error']['code']);
    }

    public function testOpenRpcAndPostmanDocumentOnlyTheirGroup(): void
    {
        self::bootKernel();
        foreach (['bfa' => ['area.shared', 'area.privateMethod'], 'bff' => ['area.shared']] as $group => $expected) {
            [$status, $doc] = $this->request('/rpc/'.$group);
            self::assertSame(200, $status);
            $methods = array_column($doc['methods'], 'name');
            sort($methods);
            sort($expected);
            self::assertSame($expected, $methods);
            self::assertSame('http://localhost/rpc/'.$group, $doc['servers'][0]['url']);
            [$status, $postman] = $this->request('/rpc/'.$group.'/postman');
            self::assertSame(200, $status);
            $json = json_encode($postman);
            self::assertStringContainsString('/rpc/'.$group, str_replace('\\/', '/', $json));
            self::assertStringNotContainsString('area.generalOnly', $json);
            if ($group === 'bff') self::assertStringNotContainsString('area.privateMethod', $json);
        }
    }

    public function testVersionsRemainIndependentAcrossGroups(): void
    {
        self::bootKernel();
        self::assertSame('v2', $this->request('/rpc/bfa/v2', 'POST', 'area.shared')[1]['result']);
        self::assertSame(-32601, $this->request('/rpc/bfa/v2', 'POST', 'area.privateMethod')[1]['error']['code']);
        self::assertSame('shared', $this->request('/rpc/bff', 'POST', 'area.shared')[1]['result']);
        [$status, $doc] = $this->request('/rpc/bfa/v2');
        self::assertSame(200, $status);
        self::assertSame(['area.shared'], array_column($doc['methods'], 'name'));
        self::assertSame('http://localhost/rpc/bfa/v2', $doc['servers'][0]['url']);
        [$status, $postman] = $this->request('/rpc/bfa/v2/postman');
        self::assertSame(200, $status);
        self::assertStringContainsString('/rpc/bfa/v2', str_replace('\\/', '/', json_encode($postman)));
    }

    public function testBatchChildProcessesKeepGroupAndVersion(): void
    {
        self::bootKernel();
        $request = Request::create('http://localhost/rpc/bfa/v2', 'POST', server: ['HTTP_UFO_RPC_TOKEN' => 'batch-token'], content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.shared'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'area.privateMethod'],
        ]));
        $response = self::$kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $results = array_column(json_decode($response->getContent(), true), null, 'id');
        self::assertArrayHasKey('result', $results[1], json_encode($results));
        self::assertSame('v2', $results[1]['result']);
        self::assertSame(-32601, $results[2]['error']['code']);
    }

    public function testCliSelectsGroupAndDefaultsToGeneral(): void
    {
        self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $application->run(new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'ufo:rpc:process',
            'json' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.privateMethod']),
            '--api-group' => 'bfa',
        ]), $output);
        self::assertSame('bfa', json_decode($output->fetch(), true)['result']);
        self::assertSame('general', self::getContainer()->get(ApiGroupContext::class)->getGroup());
        $application->run(new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'ufo:rpc:process',
            'json' => json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping']),
        ]), $output);
        self::assertSame('PONG', json_decode($output->fetch(), true)['result']);
    }

    public function testDeferredNotificationKeepsGroupAfterHttpResponse(): void
    {
        self::bootKernel();
        \Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure::$privateExecutions = 0;
        $request = Request::create('http://localhost/rpc/bfa', 'POST', content: json_encode([
            'jsonrpc' => '2.0', 'method' => 'area.privateMethod',
        ]));
        $response = self::$kernel->handle($request);
        self::assertSame(204, $response->getStatusCode());
        ob_start();
        try {
            self::$kernel->terminate($request, $response);
        } finally {
            ob_end_clean();
        }
        self::assertSame(1, \Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure::$privateExecutions);
        self::assertSame('general', self::getContainer()->get(ApiGroupContext::class)->getGroup());
    }

    public function testResponsesShareCacheBetweenGroups(): void
    {
        self::bootKernel();
        $cache = self::getContainer()->get(\Ufo\JsonRpcBundle\Server\RpcCache\RpcCacheService::class);
        $pool = self::getContainer()->get('rpc.cache.pool');
        $shared = new \Ufo\RpcObject\RpcRequest(1, 'area.shared');
        try {
            $cache->saveCacheResponse($shared, new \Ufo\RpcObject\RpcResponse(
                $shared->getId(), 'cached', cache: new \Ufo\RpcObject\RPC\Cache(environments: ['test'])
            ));
            self::assertSame('cached', $this->request('/rpc/bfa', 'POST', 'area.shared')[1]['result']);
            self::assertSame('cached', $this->request('/rpc/bff', 'POST', 'area.shared')[1]['result']);
            self::assertSame('cached', $this->request('/rpc', 'POST', 'area.shared')[1]['result']);
        } finally {
            $pool->deleteItem($cache->getCacheKey($shared));
        }
    }

    public function testLegacySecurityConfigurationProtectsAllGroups(): void
    {
        TestKernel::$rpcConfig = [
            'cache' => [], 'docs' => ['validations' => []],
            'security' => ['protected_api' => true, 'protected_doc' => false, 'clients_tokens' => ['base-token']],
        ];
        self::bootKernel();
        foreach (['/rpc', '/rpc/bfa', '/rpc/bff'] as $path) {
            self::assertSame('shared', $this->request($path, 'POST', 'area.shared', 'base-token')[1]['result']);
            self::assertSame(403, $this->request($path, 'POST', 'area.shared', 'invalid')[0]);
        }
    }

    public function testSecuritySelectsGroupProviderAndFallsBackForOtherGroups(): void
    {
        TestKernel::$rpcConfig = [
            'cache' => [],
            'docs' => ['validations' => []],
            'security' => [
                'protected_api' => true,
                'protected_doc' => true,
                'clients_tokens' => ['base-token'],
                'providers' => ['user' => ['service' => GroupTokenValidator::class, 'api_groups' => ['bfa']]],
            ],
        ];
        self::bootKernel();
        self::assertSame('bfa', $this->request('/rpc/bfa', 'POST', 'area.privateMethod', 'bfa-token')[1]['result']);
        self::assertSame(403, $this->request('/rpc/bfa', 'POST', 'area.shared', 'base-token')[0]);
        self::assertSame('shared', $this->request('/rpc/bff', 'POST', 'area.shared', 'base-token')[1]['result']);
        self::assertSame('PONG', $this->request('/rpc', 'POST', 'ping', 'base-token')[1]['result']);
        self::assertSame(200, $this->request('/rpc/bfa/v1', token: 'bfa-token')[0]);
        self::assertSame(403, $this->request('/rpc/bfa/v1/postman', token: 'base-token')[0]);
    }
}

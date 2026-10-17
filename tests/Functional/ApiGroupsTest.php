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
            'general' => ['area.shared', 'area.cached', 'area.generalOnly', 'ping'],
            'bfa' => ['area.shared', 'area.cached', 'area.privateMethod'],
            'bff' => ['area.shared', 'area.cached'],
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
        foreach (['bfa' => ['area.shared', 'area.cached', 'area.privateMethod'], 'bff' => ['area.shared', 'area.cached']] as $group => $expected) {
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
            self::assertStringContainsString('['.$group.']', $postman['info']['name']);
        }
    }

    public function testPostmanNameHasNoGroupMarkForDefaultGroup(): void
    {
        self::bootKernel();
        [$status, $postman] = $this->request('/rpc/postman');
        self::assertSame(200, $status);
        self::assertStringNotContainsString('[general]', $postman['info']['name']);
    }

    public function testVersionsRemainIndependentAcrossGroups(): void
    {
        self::bootKernel();
        self::assertSame('v2', $this->request('/rpc/bfa/v2', 'POST', 'area.shared')[1]['result']);
        self::assertSame(-32601, $this->request('/rpc/bfa/v2', 'POST', 'area.privateMethod')[1]['error']['code']);
        self::assertSame('shared', $this->request('/rpc/bff', 'POST', 'area.shared')[1]['result']);
        [$status, $doc] = $this->request('/rpc/bfa/v2');
        self::assertSame(200, $status);
        $methods = array_column($doc['methods'], 'name');
        sort($methods);
        self::assertSame(['area.cached', 'area.shared'], $methods);
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

    private function batchConfig(array $batch): array
    {
        return [
            'cache' => [],
            'security' => ['protected_api' => false, 'protected_doc' => false],
            'docs' => ['project_name' => 'test-rpc', 'validations' => []],
            'batch' => $batch,
        ];
    }

    public function testInlineBatchGivesTheSameResultsAsProcessMode(): void
    {
        TestKernel::$rpcConfig = $this->batchConfig(['process' => false]);
        self::bootKernel();
        $request = Request::create('http://localhost/rpc/bfa/v2', 'POST', server: ['HTTP_UFO_RPC_TOKEN' => 'batch-token'], content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.shared'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'area.privateMethod'],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'area.missing'],
        ]));
        $response = self::$kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $results = array_column(json_decode($response->getContent(), true), null, 'id');
        self::assertSame('v2', $results[1]['result'], json_encode($results));
        self::assertSame(-32601, $results[2]['error']['code']);
        self::assertSame(-32601, $results[3]['error']['code']);
    }

    /**
     * Separate process: the notification runs inside this PHP process, and the static
     * Transformer::getDefault() serializer of rpc-objects then breaks the next kernel booted in the
     * same process (RpcNotificationRequest is routed to DateTimeNormalizer). Real requests are not
     * affected: one kernel per process under FPM; with one kernel serving many requests it works.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testInlineBatchExecutesNotificationOnce(): void
    {
        TestKernel::$rpcConfig = $this->batchConfig(['process' => false]);
        self::bootKernel();
        \Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure::$privateExecutions = 0;
        $request = Request::create('http://localhost/rpc/bfa', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.shared'],
            ['jsonrpc' => '2.0', 'method' => 'area.privateMethod'],
        ]));
        $response = self::$kernel->handle($request);
        self::$kernel->terminate($request, $response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, \Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure::$privateExecutions);
        // JSON-RPC 2.0: no response object for the notification.
        self::assertSame([['id' => 1, 'result' => 'shared', 'jsonrpc' => '2.0']], json_decode($response->getContent(), true));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testInlineBatchOfNotificationsOnlyReturnsNoContent(): void
    {
        TestKernel::$rpcConfig = $this->batchConfig(['process' => false]);
        self::bootKernel();
        \Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure::$privateExecutions = 0;
        $response = self::$kernel->handle(Request::create('http://localhost/rpc/bfa', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'method' => 'area.privateMethod'],
            ['jsonrpc' => '2.0', 'method' => 'area.privateMethod'],
        ])));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame(2, \Ufo\JsonRpcBundle\Tests\Fixtures\Procedures\GroupedProcedure::$privateExecutions);
    }

    /**
     * No token header on purpose: an unprotected API must accept a process-mode batch without a token.
     */
    public function testProcessBatchSkipsNotificationInResponse(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('http://localhost/rpc/bfa', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'area.shared'],
            ['jsonrpc' => '2.0', 'method' => 'area.privateMethod'],
        ])));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([['id' => 1, 'result' => 'shared', 'jsonrpc' => '2.0']], json_decode($response->getContent(), true));
    }

    public function testProcessBatchOfNotificationsOnlyReturnsNoContent(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('http://localhost/rpc/bfa', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'method' => 'area.privateMethod'],
            ['jsonrpc' => '2.0', 'method' => 'area.shared'],
        ])));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testDisabledBatchReturnsOneError(): void
    {
        TestKernel::$rpcConfig = $this->batchConfig(['enabled' => false]);
        self::bootKernel();
        [$status, $body] = $this->request('/rpc', 'POST', 'ping');
        self::assertSame('PONG', $body['result'], 'single requests still work');

        $response = self::$kernel->handle(Request::create('http://localhost/rpc', 'POST', content: json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
        ])));
        $body = json_decode($response->getContent(), true);
        self::assertSame(-32600, $body['error']['code']);
        self::assertSame('Batch requests are disabled', $body['error']['message']);
        self::assertSame(\Ufo\JsonRpcBundle\EventDrivenModel\Listeners\SymfonyErrorListener::BATCH_DISABLED, $body['id']);
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

    public function testCliReadsRequestFromStdin(): void
    {
        self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']));
        rewind($stdin);
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'ufo:rpc:process',
            'json' => \Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand::JSON_FROM_STDIN,
        ]);
        $input->setStream($stdin);
        $application->run($input, $output);
        self::assertSame('PONG', json_decode($output->fetch(), true)['result']);
    }

    public function testDocsAreCachedWithEtagOutsideDebug(): void
    {
        self::bootKernel(['debug' => false]);
        $first = self::$kernel->handle(Request::create('http://localhost/rpc/bfa'));
        $second = self::$kernel->handle(Request::create('http://localhost/rpc/bfa'));
        self::assertSame(200, $first->getStatusCode());
        self::assertNotNull($first->getEtag());
        self::assertSame($first->getEtag(), $second->getEtag());
        self::assertSame($first->getContent(), $second->getContent());

        $notModified = self::$kernel->handle(Request::create('http://localhost/rpc/bfa', server: ['HTTP_IF_NONE_MATCH' => $first->getEtag()]));
        self::assertSame(304, $notModified->getStatusCode());

        $otherGroup = self::$kernel->handle(Request::create('http://localhost/rpc/bff'));
        self::assertNotSame($first->getEtag(), $otherGroup->getEtag());
        self::assertNotSame($first->getContent(), $otherGroup->getContent());
    }

    public function testCliReadsRequestFromEnv(): void
    {
        self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        putenv(\Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand::REQUEST_ENV.'='.json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']));
        try {
            $application->run(new \Symfony\Component\Console\Input\ArrayInput([
                'command' => 'ufo:rpc:process',
                'json' => \Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand::JSON_FROM_ENV,
            ]), $output);
        } finally {
            putenv(\Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand::REQUEST_ENV);
        }
        self::assertSame('PONG', json_decode($output->fetch(), true)['result']);
    }

    public function testXmlHttpRequestIsHandledAsRegularCall(): void
    {
        self::bootKernel();
        $request = Request::create('http://localhost/rpc', 'POST', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], content: json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']));
        $response = self::$kernel->handle($request);
        self::assertSame('PONG', json_decode($response->getContent(), true)['result']);

        $preflight = self::$kernel->handle(Request::create('http://localhost/rpc', 'OPTIONS'));
        self::assertSame(200, $preflight->getStatusCode());
        self::assertSame('', $preflight->getContent());
    }

    private function cliCall(string $group, array $body): array
    {
        return json_decode($this->cliOutput($group, $body), true);
    }

    private function cliOutput(string $group, array $body): string
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $application->run(new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'ufo:rpc:process',
            'json' => json_encode(['jsonrpc' => '2.0', 'id' => 1] + $body),
            '--api-group' => $group,
        ]), $output);
        return $output->fetch();
    }

    public function testAutowiredMethodArgumentsAreInjected(): void
    {
        self::bootKernel();
        // Custom separator from #[RPC\Info(concat: '_')].
        self::assertStringContainsString('Router', $this->cliCall('tools', ['method' => 'tools_router'])['result']);
        self::assertSame('ann:router', $this->cliCall('tools', ['method' => 'tools_greet', 'params' => ['name' => 'ann']])['result']);
        self::assertSame('bob:router', $this->cliCall('tools', ['method' => 'tools_greet', 'params' => ['bob']])['result']);
        // The default "." separator does not apply (in debug the CLI also prints the trace, so not pure JSON).
        self::assertStringContainsString('"code": -32601', $this->cliOutput('tools', ['method' => 'tools.router']));
    }

    public function testDeprecatedMethodIsMarkedInOpenRpc(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->get(ApiGroupContext::class)->setGroup('tools');
        $service = $container->get(ServiceMap::class)->getService('tools_old');
        self::assertTrue($service->isDeprecated());

        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $router->method('generate')->willReturn('/rpc/tools');
        $adapter = new \Ufo\JsonRpcBundle\DocAdapters\Outputs\OpenRpcAdapter(
            $container->get(ServiceMap::class),
            $container->get(\Ufo\JsonRpcBundle\ConfigService\RpcMainConfig::class),
            $container->get(\Ufo\JsonRpcBundle\ParamConvertors\ChainParamConvertor::class),
            $router,
            $container->get(\Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\EnumProcessor\EnumsHolder::class),
            $container->get(ApiGroupContext::class),
        );
        // As ApiController does: the spec objects are published as JSON.
        $methods = array_column(json_decode(json_encode($adapter->adapt()), true)['methods'], null, 'name');
        self::assertTrue($methods['tools_old']['deprecated'] ?? false);
        self::assertArrayNotHasKey('deprecated', $methods['tools_router']);
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
        $shared = new \Ufo\RpcObject\RpcRequest(1, 'area.cached');
        try {
            $cache->saveCacheResponse($shared, new \Ufo\RpcObject\RpcResponse(
                $shared->getId(), 'cached', cache: new \Ufo\RpcObject\RPC\Cache(environments: ['test'])
            ));
            // The same implementation is shared between API groups (BFFs).
            self::assertSame('cached', $this->request('/rpc/bfa', 'POST', 'area.cached')[1]['result']);
            self::assertSame('cached', $this->request('/rpc/bff', 'POST', 'area.cached')[1]['result']);
            self::assertSame('cached', $this->request('/rpc', 'POST', 'area.cached')[1]['result']);
            // Methods without #[Cache] never read the cache.
            self::assertSame('shared', $this->request('/rpc/bfa', 'POST', 'area.shared')[1]['result']);
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

    public function testOmittedBaseTokensInjectAnEmptyList(): void
    {
        TestKernel::$rpcConfig = [
            'cache' => [], 'docs' => ['validations' => []],
            'security' => [
                'protected_api' => true,
                'providers' => ['internal' => ['api_groups' => ['bfa'], 'clients_tokens' => ['internal-token']]],
            ],
        ];
        self::bootKernel();
        self::assertSame([], self::getContainer()->getParameter('ufo_json_rpc.security.clients_tokens'));
        self::assertSame(403, $this->request('/rpc', 'POST', 'ping', 'internal-token')[0]);
        self::assertSame(200, $this->request('/rpc/bfa', 'POST', 'area.shared', 'internal-token')[0]);
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

    public function testEnvTokensAreIsolatedByGroupForRequestsAndDocumentation(): void
    {
        $_ENV['RPC_TEST_INTERNAL_TOKEN'] = 'internal-env-token';
        $_ENV['RPC_TEST_PUBLIC_TOKEN'] = 'public-env-token';
        try {
            TestKernel::$rpcConfig = [
                'cache' => [], 'docs' => ['validations' => []],
                'security' => [
                    'protected_api' => true,
                    'protected_doc' => true,
                    'clients_tokens' => ['base-token'],
                    'providers' => [
                        'internal' => ['api_groups' => ['bfa'], 'clients_tokens' => ['%env(resolve:RPC_TEST_INTERNAL_TOKEN)%']],
                        'public' => ['api_groups' => ['bff'], 'clients_tokens' => ['%env(resolve:RPC_TEST_PUBLIC_TOKEN)%']],
                    ],
                ],
            ];
            self::bootKernel();
            $tokens = ['/rpc' => 'base-token', '/rpc/bfa' => 'internal-env-token', '/rpc/bff' => 'public-env-token'];
            foreach ($tokens as $path => $validToken) {
                foreach ($tokens as $token) {
                    $expectedStatus = $token === $validToken ? 200 : 403;
                    self::assertSame($expectedStatus, $this->request($path, 'POST', 'area.shared', $token)[0]);
                    self::assertSame($expectedStatus, $this->request($path, token: $token)[0]);
                    self::assertSame($expectedStatus, $this->request($path.'/postman', token: $token)[0]);
                }
            }
        } finally {
            self::ensureKernelShutdown();
            unset($_ENV['RPC_TEST_INTERNAL_TOKEN'], $_ENV['RPC_TEST_PUBLIC_TOKEN']);
        }
    }

}

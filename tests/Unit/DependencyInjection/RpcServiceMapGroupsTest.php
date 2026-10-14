<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\DependencyInjection\CompilerPass\RpcServiceMapPass;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\Fillers\ChainServiceFiller;
use Ufo\RpcObject\RPC;

class RpcServiceMapGroupsTest extends TestCase
{
    private function pass(): RpcServiceMapPass
    {
        return new class extends RpcServiceMapPass {
            protected function getServiceFiller(): ChainServiceFiller
            {
                return new ChainServiceFiller([]);
            }
        };
    }

    public function testDifferentGroupsCanRegisterDifferentClassesWithTheSameRpcName(): void
    {
        $pass = $this->pass();
        $pass->processServiceMap(new \ReflectionClass(FirstGroupProcedure::class));
        $pass->processServiceMap(new \ReflectionClass(SecondGroupProcedure::class));
        $maps = (new \ReflectionProperty($pass, 'groupMaps'))->getValue($pass);
        self::assertSame(FirstGroupProcedure::class, $maps['first']['collision.ping:v1']->getProcedureFQCN());
        self::assertSame(SecondGroupProcedure::class, $maps['second']['collision.ping:v1']->getProcedureFQCN());
    }

    public function testDuplicateRpcNameInTheSameGroupIsRejected(): void
    {
        $pass = $this->pass();
        $pass->processServiceMap(new \ReflectionClass(FirstGroupProcedure::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already registered in API group "first"');
        $pass->processServiceMap(new \ReflectionClass(DuplicateGroupProcedure::class));
    }
}

#[RPC\Info('collision')]
#[RPC\APIGroups(['first'], false)]
class FirstGroupProcedure implements IRpcService
{
    public function ping(): string { return 'first'; }
}

#[RPC\Info('collision')]
#[RPC\APIGroups(['second'], false)]
class SecondGroupProcedure implements IRpcService
{
    public function ping(): string { return 'second'; }
}

#[RPC\Info('collision')]
#[RPC\APIGroups(['first'], false)]
class DuplicateGroupProcedure implements IRpcService
{
    public function ping(): string { return 'duplicate'; }
}

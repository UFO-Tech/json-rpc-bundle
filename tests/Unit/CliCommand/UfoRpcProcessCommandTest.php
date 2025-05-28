<?php

namespace  Ufo\JsonRpcBundle\Tests\Unit\CliCommand;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\ConfigService\RpcSecurityConfig;
use Ufo\JsonRpcBundle\Tests\Unit\ConfigHolderTrait;

class UfoRpcProcessCommandTest extends TestCase
{
    use ConfigHolderTrait;

    private UfoRpcProcessCommand $command;


    protected function setUp(): void
    {
        $this->setUpCommand();
    }

    protected function setUpCommand(array $config = []): void
    {
        $this->setConfig($config);
        $this->command = new UfoRpcProcessCommand($this->rpcMainConfig);
    }

    public function testConfigureAddsJsonArgument(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('json'));
        $argument = $definition->getArgument('json');
        $this->assertSame('[]', $argument->getDefault());
    }

    public function testConfigureAddsTokenOptionWithProtectedApiEnabled(): void
    {
        $this->setUpCommand([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => true,
                RpcSecurityConfig::TOKEN_NAME => 'testtoken'
            ]
        ]);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('testtoken')); // Lowercase token name
        $option = $definition->getOption('testtoken');
        $this->assertSame('', $option->getDefault());
    }

    public function testConfigureAddsTokenOptionWithProtectedApiDisabled(): void
    {
        $this->setUpCommand([
            RpcSecurityConfig::NAME => [
                RpcSecurityConfig::PROTECTED_API => false,
                RpcSecurityConfig::TOKEN_NAME => 'testtoken'
            ]
        ]);
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('testtoken')); // Lowercase token name
        $option = $definition->getOption('testtoken');
        $this->assertNull($option->getDefault());
    }

    public function testConfigureAddsAsyncOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('async'));
        $option = $definition->getOption('async');
        $this->assertNull($option->getDefault());
    }

}
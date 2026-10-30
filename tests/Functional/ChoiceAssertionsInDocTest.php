<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Tests\Fixtures\EnumDocKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;

/** x-ufo-assertions не віддає Choice з callback на серверний клас, якого немає в SDK. */
class ChoiceAssertionsInDocTest extends KernelTestCase
{
    /** @var array<string, mixed> */
    private array $doc;

    protected static function getKernelClass(): string { return EnumDocKernel::class; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('http://localhost/rpc', 'GET'));
        $this->doc = json_decode($response->getContent(), true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testChoiceWithListIsCopied(): void
    {
        self::assertSame("new Assert\\Choice(choices: ['a', 'b'])", $this->assertions('choices.listed', 'letter'));
    }

    public function testChoiceWithEnumCallbackIsCopied(): void
    {
        self::assertSame("new Assert\\Choice(callback: [Color::class, 'cases'])", $this->assertions('choices.fromEnum', 'color'));
    }

    public function testChoiceWithClassCallbackIsNotCopied(): void
    {
        self::assertNull($this->assertions('choices.fromClass', 'shipping'));
    }

    public function testChoiceWithStringCallbackIsNotCopied(): void
    {
        self::assertNull($this->assertions('choices.stringCallback', 'shipping'));
    }

    public function testNestedChoiceWithClassCallbackIsNotCopied(): void
    {
        self::assertNull($this->assertions('choices.nestedFromClass', 'shippings'));
    }

    public function testDocDoesNotNameTheServerClass(): void
    {
        self::assertStringNotContainsString('ShippingMethods', json_encode($this->doc));
    }

    private function assertions(string $method, string $param): ?string
    {
        foreach ($this->doc['methods'] as $item) {
            if ($item['name'] !== $method) continue;
            foreach ($item['params'] as $p) {
                if ($p['name'] === $param) {
                    return $p['x-ufo-assertions'] ?? null;
                }
            }
        }
        self::fail(sprintf('Param %s of %s not found in the document', $param, $method));
    }
}

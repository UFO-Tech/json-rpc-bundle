<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Tests\Fixtures\ConstraintsFlowKernel;
use Ufo\JsonRpcBundle\Tests\Fixtures\TestKernel;

use function array_key_exists;
use function json_decode;
use function json_encode;

/**
 * Повний шлях запиту для #[RPC\Assertions]: іменовані й позиційні параметри, передані й пропущені,
 * значення за замовчуванням, null, Optional. Очікування — бажана поведінка.
 */
class ConstraintsFlowTest extends KernelTestCase
{
    private const string UUID = '550e8400-e29b-41d4-a716-446655440000';

    protected static function getKernelClass(): string { return ConstraintsFlowKernel::class; }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        TestKernel::$rpcConfig = [];
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    /** @return array<string, array{0: string, 1: array<int|string, mixed>|null, 2: array{result?: mixed, error?: string|null}}> */
    public static function cases(): array
    {
        return [
            'range named valid' => ['range', ['id' => 'a', 'scale' => 5], ['result' => 5]],
            'range named invalid' => ['range', ['id' => 'a', 'scale' => 20], ['error' => 'scale']],
            'range named omitted' => ['range', ['id' => 'a'], ['result' => 2]],
            'range named explicit null' => ['range', ['id' => 'a', 'scale' => null], ['error' => 'scale']],
            'range positional valid' => ['range', ['a', 5], ['result' => 5]],
            'range positional invalid' => ['range', ['a', 20], ['error' => 'scale']],
            'range positional omitted' => ['range', ['a'], ['result' => 2]],

            'bad default named omitted' => ['rangeBadDefault', ['id' => 'a'], ['error' => 'scale']],
            'bad default named valid' => ['rangeBadDefault', ['id' => 'a', 'scale' => 3], ['result' => 3]],
            'bad default positional omitted' => ['rangeBadDefault', ['a'], ['error' => 'scale']],
            'bad default positional valid' => ['rangeBadDefault', ['a', 3], ['result' => 3]],

            'optional+range named invalid' => ['optionalRange', ['id' => 'a', 'scale' => 20], ['error' => 'scale']],
            'optional+range named omitted' => ['optionalRange', ['id' => 'a'], ['result' => 2]],
            'optional+range positional invalid' => ['optionalRange', ['a', 20], ['error' => 'scale']],
            'optional+range positional omitted' => ['optionalRange', ['a'], ['result' => 2]],

            'uuid named omitted' => ['nullableUuid', ['id' => 'a'], ['result' => null]],
            'uuid named null' => ['nullableUuid', ['id' => 'a', 'token' => null], ['result' => null]],
            'uuid named valid' => ['nullableUuid', ['id' => 'a', 'token' => self::UUID], ['result' => self::UUID]],
            'uuid named invalid' => ['nullableUuid', ['id' => 'a', 'token' => 'bad'], ['error' => 'token']],
            'uuid positional omitted' => ['nullableUuid', ['a'], ['result' => null]],
            'uuid positional valid' => ['nullableUuid', ['a', self::UUID], ['result' => self::UUID]],
            'uuid positional invalid' => ['nullableUuid', ['a', 'bad'], ['error' => 'token']],

            'notBlank null default named omitted' => ['notBlankNullDefault', ['id' => 'a'], ['error' => 'name']],
            'notBlank null default named value' => ['notBlankNullDefault', ['id' => 'a', 'name' => 'x'], ['result' => 'x']],
            'notBlank null default named empty' => ['notBlankNullDefault', ['id' => 'a', 'name' => ''], ['error' => 'name']],
            'notBlank null default positional omitted' => ['notBlankNullDefault', ['a'], ['error' => 'name']],
            'notBlank null default positional value' => ['notBlankNullDefault', ['a', 'x'], ['result' => 'x']],
            'notBlank null default positional empty' => ['notBlankNullDefault', ['a', ''], ['error' => 'name']],

            'required named valid' => ['required', ['name' => 'abc'], ['result' => 'abc']],
            'required named too short' => ['required', ['name' => 'ab'], ['error' => 'name']],
            'required named blank' => ['required', ['name' => ''], ['error' => 'name']],
            'required positional valid' => ['required', ['abc'], ['result' => 'abc']],
            'required positional too short' => ['required', ['ab'], ['error' => 'name']],
            'required omitted' => ['required', null, ['error' => null]],

            'variadic positional keeps every value' => ['variadic', ['a', 'x', 'y'], ['result' => ['a', ['x', 'y']]]],
            'variadic positional single value' => ['variadic', ['a', 'x'], ['result' => ['a', ['x']]]],

            'no params sent, bad default' => ['noParams', null, ['error' => 'scale']],
            'no params sent, named valid' => ['noParams', ['scale' => 3], ['result' => 3]],
            'no params sent, positional valid' => ['noParams', [3], ['result' => 3]],
        ];
    }

    /**
     * @param array<int|string, mixed>|null $params null — запит без поля params
     * @param array{result?: mixed, error?: string|null} $expected error: null — лише код -32602
     */
    #[DataProvider('cases')]
    public function testConstraintsFlow(string $method, ?array $params, array $expected): void
    {
        $response = $this->call('flow.' . $method, $params);
        $actual = json_encode($response);

        if (array_key_exists('error', $expected)) {
            self::assertArrayHasKey('error', $response, $actual);
            self::assertSame(-32602, $response['error']['code'] ?? null, $actual);
            if ($expected['error'] !== null) {
                self::assertArrayHasKey($expected['error'], $response['error']['data'] ?? [], $actual);
            }

            return;
        }

        self::assertArrayNotHasKey('error', $response, $actual);
        self::assertArrayHasKey('result', $response, $actual);
        self::assertSame($expected['result'], $response['result'], $actual);
    }

    /** @param array<int|string, mixed>|null $params */
    private function call(string $method, ?array $params): array
    {
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method];
        if ($params !== null) {
            $payload['params'] = $params;
        }
        $response = self::$kernel->handle(Request::create('http://localhost/rpc', 'POST', content: json_encode($payload)));

        return json_decode($response->getContent(), true);
    }
}

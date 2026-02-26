<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DocAdapters\Outputs\Postman;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Folder;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Header;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Info;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Server;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Variable;

class BlocksTest extends TestCase
{
    public function testVariableAndHeaderToArray(): void
    {
        $variable = new Variable('base_url', 'https://api.example.com');
        $header = new Header('Ufo-RPC-Token', 'secret');

        $this->assertSame([
            'key' => 'base_url',
            'value' => 'https://api.example.com',
        ], $variable->toArray());
        $this->assertSame([
            'key' => 'Ufo-RPC-Token',
            'value' => 'secret',
        ], $header->toArray());
    }

    public function testServerToArrayWithPort(): void
    {
        $server = new Server('https://api.example.com:8443/rpc/v1');

        $this->assertSame([
            'raw' => '{{base_url}}/rpc/v1',
            'protocol' => 'https',
            'host' => ['api', 'example', 'com'],
            'path' => ['rpc', 'v1'],
            'port' => 8443,
        ], $server->toArray());
    }

    public function testInfoToArrayContainsVersionAndDocVersion(): void
    {
        $info = new Info('API', 'Desc', 'schema-url', '1.0.0', 'fixed-doc-version');
        $data = $info->toArray()['info'];

        $this->assertSame('API 1.0.0 (fixed-doc-version)', $data['name']);
        $this->assertSame('Desc', $data['description']);
        $this->assertSame('schema-url', $data['schema']);
        $this->assertSame('1.0.0', $data['version']);
        $this->assertArrayHasKey('_postman_id', $data);
    }

    public function testFolderStoresMethodsAndRendersItems(): void
    {
        $method = new Method(
            'ping',
            'Ping method',
            [new Header('Content-Type', 'application/json')],
            new Server('https://api.example.com/rpc')
        );

        $folder = new Folder('Core');
        $folder->addMethod($method);

        $this->assertCount(1, $folder->getMethods());
        $data = $folder->toArray();
        $this->assertSame('Core', $data['name']);
        $this->assertCount(1, $data['item']);
        $this->assertSame('ping', $data['item'][0]['name']);
    }
}


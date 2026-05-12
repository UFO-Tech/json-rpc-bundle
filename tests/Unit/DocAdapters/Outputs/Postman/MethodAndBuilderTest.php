<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\DocAdapters\Outputs\Postman;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Header;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Server;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecFiller;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Info;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Variable;
use Ufo\JsonRpcBundle\Server\ServiceMap\Reflections\ParamDefinition;

class MethodAndBuilderTest extends TestCase
{
    public function testMethodToArrayBuildsJsonRpcBodyAndParams(): void
    {
        $method = new Method(
            'sum',
            'Sum method',
            [new Header('Content-Type', 'application/json')],
            new Server('https://api.example.com/rpc')
        );

        $requiredParam = new ParamDefinition('id', ['integer'], 'int');
        $optionalParam = (new ParamDefinition('query', ['string'], 'string'))->setDefault('all');
        $method->addParam($requiredParam)->addParam($optionalParam);

        $data = $method->toArray();
        $body = json_decode($data['request']['body']['raw'], true);

        $this->assertSame('sum', $data['name']);
        $this->assertSame('POST', $data['request']['method']);
        $this->assertSame('{{base_url}}/rpc', $data['request']['url']);
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertSame('sum', $body['method']);
        $this->assertSame(0, $body['params']['id']);
        $this->assertSame('all', $body['params']['query']);
        $this->assertArrayHasKey('id', $body);
    }

    public function testPostmanSpecFillerCombinesFoldersMethodsAndVariables(): void
    {
        $info = new Info('API', 'Desc', 'schema', '1.0', 'doc-v1');
        $filler = new PostmanSpecFiller($info);
        $methodA = new Method('ping', 'Ping', [], new Server('https://api.example.com/rpc'));
        $methodB = new Method('status', 'Status', [], new Server('https://api.example.com/rpc'));

        $filler->addToFolder('Core', $methodA);
        $filler->addMethod($methodB);
        $filler->addVariable('base_url', new Variable('base_url', 'https://api.example.com'));

        $this->assertSame($info, $filler->getInfo());
        $this->assertCount(1, $filler->getMethods());
        $this->assertCount(1, $filler->getFolders());

        $data = $filler->toArray();

        $this->assertArrayHasKey('info', $data);
        $this->assertCount(2, $data['item']);
        $this->assertCount(1, $data['variables']);
        $this->assertSame('base_url', $data['variables'][0]['key']);
    }

    public function testPostmanSpecBuilderBuildsCollectionWithFolderAndVariables(): void
    {
        $builder = PostmanSpecBuilder::createBuilder('API', 'Desc', '1.0.0');
        $builder->addServer('https://api.example.com/rpc');

        $method = $builder->buildMethod(
            name: 'findUsers',
            description: 'Find users',
            headers: [['key' => 'X-Auth', 'value' => 'token']],
            folder: 'Users'
        );
        $builder->buildParam($method, new ParamDefinition('limit', ['integer'], 'int'));
        $builder->addVariable('base_url', 'https://api.example.com');

        $collection = $builder->build();

        $this->assertArrayHasKey('info', $collection);
        $this->assertArrayHasKey('item', $collection);
        $this->assertArrayHasKey('variables', $collection);
        $this->assertCount(1, $collection['item']);
        $this->assertSame('Users', $collection['item'][0]['name']);
        $this->assertSame('base_url', $collection['variables'][0]['key']);
    }
}

<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\SpecialRpcParamsEnum;

class RequestCarrierTest extends TestCase
{
    public function testGetSpecialParamsReturnsParamsWhenRequestObjectIsAvailable(): void
    {
        // Arrange
        $expectedParams = ['key' => 'value'];
        $mockRpcRequest = $this->createMock(RpcRequest::class);
        $mockRpcRequest->method('getSpecialParams')->willReturn($expectedParams);
        $mockCarrier = $this->createMock(IRpcRequestCarrier::class);
        $mockCarrier->method('getRequestObject')->willReturn($mockRpcRequest);
        $requestCarrier = new RequestCarrier();
        $requestCarrier->setCarrier($mockCarrier);
        $specialParams = $requestCarrier->getSpecialParams();
        $this->assertEquals([
            SpecialRpcParamsEnum::TIMEOUT->value => SpecialRpcParamsEnum::DEFAULT_TIMEOUT,
            SpecialRpcParamsEnum::IGNORE_CACHE->value => false,
        ], $specialParams);
    }

    public function testGetSpecialParamsReturnsEmptyArrayWhenNoCarrierIsSet(): void
    {
        $requestCarrier = new RequestCarrier();
        $specialParams = $requestCarrier->getSpecialParams();
        $this->assertEquals([
            SpecialRpcParamsEnum::TIMEOUT->value => SpecialRpcParamsEnum::DEFAULT_TIMEOUT,
            SpecialRpcParamsEnum::IGNORE_CACHE->value => false,
        ], $specialParams);
    }

    public function testGetSpecialParamsReturnsEmptyArrayWhenCarrierThrowsWrongWayException(): void
    {
        $mockCarrier = $this->createMock(IRpcRequestCarrier::class);
        $mockCarrier->method('getRequestObject')->willThrowException(new WrongWayException());
        $requestCarrier = new RequestCarrier();
        $requestCarrier->setCarrier($mockCarrier);
        $specialParams = $requestCarrier->getSpecialParams();
        $this->assertEquals([
            SpecialRpcParamsEnum::TIMEOUT->value => SpecialRpcParamsEnum::DEFAULT_TIMEOUT,
            SpecialRpcParamsEnum::IGNORE_CACHE->value => false,
        ], $specialParams);
    }

    public function testGetRequestObjectThrowsWhenCarrierIsMissing(): void
    {
        $requestCarrier = new RequestCarrier();

        $this->expectException(WrongWayException::class);
        $requestCarrier->getRequestObject();
    }

    public function testGetBatchRequestObjectThrowsWhenCarrierIsMissing(): void
    {
        $requestCarrier = new RequestCarrier();

        $this->expectException(WrongWayException::class);
        $requestCarrier->getBatchRequestObject();
    }

    public function testGetRequestObjectAndBatchAreDelegatedToCarrier(): void
    {
        $rpcRequest = RpcRequest::fromArray([
            'id' => 1,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]);
        $batch = RpcBatchRequest::fromJson(json_encode([[
            'id' => 2,
            'jsonrpc' => '2.0',
            'method' => 'main.ping',
        ]]));

        $mockCarrier = $this->createMock(IRpcRequestCarrier::class);
        $mockCarrier->method('getRequestObject')->willReturn($rpcRequest);
        $mockCarrier->method('getBatchRequestObject')->willReturn($batch);

        $requestCarrier = new RequestCarrier();
        $requestCarrier->setCarrier($mockCarrier);

        $this->assertSame($rpcRequest, $requestCarrier->getRequestObject());
        $this->assertSame($batch, $requestCarrier->getBatchRequestObject());
        $this->assertSame($mockCarrier, $requestCarrier->getCarrier());
    }

    public function testSetParamAndResetParamsAffectSpecialParams(): void
    {
        $requestCarrier = new RequestCarrier();

        $requestCarrier->setParam(SpecialRpcParamsEnum::TIMEOUT->value, 12);
        $requestCarrier->setParam(SpecialRpcParamsEnum::IGNORE_CACHE->value, true);

        $this->assertSame([
            SpecialRpcParamsEnum::TIMEOUT->value => 12,
            SpecialRpcParamsEnum::IGNORE_CACHE->value => true,
        ], $requestCarrier->getSpecialParams());

        $requestCarrier->resetParams();

        $this->assertSame([
            SpecialRpcParamsEnum::TIMEOUT->value => SpecialRpcParamsEnum::DEFAULT_TIMEOUT,
            SpecialRpcParamsEnum::IGNORE_CACHE->value => false,
        ], $requestCarrier->getSpecialParams());
    }

}

<?php

namespace Ufo\JsonRpcBundle\Tests\Unit\Server\RequestPrepare;

use PHPUnit\Framework\TestCase;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcRequest;

class RequestCarrierTest extends TestCase
{
    public function testGetSpecialParamsReturnsParamsWhenRequestObjectIsAvailable(): void
    {
        // Arrange
        $expectedParams = ['key' => 'value'];
        $mockRpcRequest = $this->createMock(RpcRequest::class);
        $mockRpcRequest->method('getParams')->willReturn($expectedParams);
        $mockCarrier = $this->createMock(IRpcRequestCarrier::class);
        $mockCarrier->method('getRequestObject')->willReturn($mockRpcRequest);
        $requestCarrier = new RequestCarrier();
        $requestCarrier->setCarrier($mockCarrier);
        $specialParams = $requestCarrier->getSpecialParams();
        $this->assertEquals(['key' => 'value'], $specialParams);
    }

    public function testGetSpecialParamsReturnsEmptyArrayWhenNoCarrierIsSet(): void
    {
        $requestCarrier = new RequestCarrier();
        $specialParams = $requestCarrier->getSpecialParams();
        $this->assertEquals([], $specialParams);
    }

    public function testGetSpecialParamsReturnsEmptyArrayWhenCarrierThrowsWrongWayException(): void
    {
        $mockCarrier = $this->createMock(IRpcRequestCarrier::class);
        $mockCarrier->method('getRequestObject')->willThrowException(new WrongWayException());
        $requestCarrier = new RequestCarrier();
        $requestCarrier->setCarrier($mockCarrier);
        $specialParams = $requestCarrier->getSpecialParams();
        $this->assertEquals([], $specialParams);
    }

}
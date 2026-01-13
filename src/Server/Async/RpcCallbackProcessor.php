<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RpcFromHttp;
use Ufo\RpcError\RpcAsyncRequestException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ufo\RpcObject\IRpcSpecialParamHandler;
use Ufo\RpcObject\RpcRequest;

class RpcCallbackProcessor
{

    public function __construct(
        protected HttpClientInterface $client,
        protected SerializerInterface $serializer,
        protected RequestCarrier $requestCarrier,
    ) {}

    public function process(RpcRequest $request): void
    {
        try {
            $error = false;

            $carrier = $this->requestCarrier->getCarrier();

            $httpRequest = method_exists($carrier, 'getHttpRequest') ? $carrier->getHttpRequest() : null;

            $headerBag = $httpRequest?->headers ?? new HeaderBag();

            $headers = array_map(function ($values) {
                return is_array($values) ? implode(', ', $values) : (string) $values;
            }, $headerBag->all());

            $headers['Content-Type'] = 'application/json';
            $headers['Accept'] = 'application/json';

            $response = $this->client->request('POST', $request->getRpcParams()->getCallbackObject()->getTarget(), [
                'headers' => $headers,
                'body'    => $this->serializer->serialize($request->getResponseObject(), 'json', context: [
                    AbstractNormalizer::GROUPS => $request->getResponseObject()?->getResponseSignature(),
                ]),
            ]);
            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaders()['content-type'][0];
            $content = $response->getContent();
        } catch (Throwable $e) {
            throw new RpcAsyncRequestException($e->getMessage(), $e->getCode());
        }
    }

}

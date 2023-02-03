<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\RpcError\RpcAsyncRequestException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ufo\RpcObject\RpcRequest;

class RpcCallbackProcessor
{

    public function __construct(
        protected HttpClientInterface $client,
        protected SerializerInterface $serializer
    )
    {
    }

    public function process(RpcRequest $request)
    {
        try {
            $error = false;

            $response = $this->client->request(
                'POST',
                $request->getRpcParams()->getCallbackObject()->getTarget(),
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->normalize(
                        $request->getResponseObject(),
                        context: [
                            AbstractNormalizer::GROUPS => $request->getResponseObject()->getResponseSignature()
                        ]
                    )
                ]
            );

            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaders()['content-type'][0];
            $content = $response->getContent();

        } catch (\Throwable $e) {
            throw new RpcAsyncRequestException($e->getMessage(), $e->getCode());
        }

    }
}

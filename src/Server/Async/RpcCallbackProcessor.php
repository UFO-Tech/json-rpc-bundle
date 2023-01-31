<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\JsonRpcBundle\Server\RpcRequestObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RpcCallbackProcessor
{

    public function __construct(
        protected HttpClientInterface $client,
        protected SerializerInterface $serializer
    )
    {
    }

    public function process(RpcRequestObject $requestObject)
    {
        try {
            $error = false;

            $response = $this->client->request(
                'POST',
                $requestObject->getRpcParams()->getCallbackObject()->getTarget(),
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body' => $this->serializer->normalize(
                        $requestObject->getResponseObject(),
                        context: [
                            AbstractNormalizer::GROUPS => $requestObject->getResponseObject()->getResponseSignature()
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

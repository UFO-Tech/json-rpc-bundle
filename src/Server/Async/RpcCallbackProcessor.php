<?php

namespace Ufo\JsonRpcBundle\Server\Async;

use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\RpcError\RpcAsyncRequestException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Ufo\RpcObject\RpcRequest;

use function array_key_exists;
use function is_array;
use function strtolower;

class RpcCallbackProcessor
{
    private const float CALLBACK_TIMEOUT = 10.0;
    private const float CALLBACK_MAX_DURATION = 15.0;

    /**
     * Whitelisted headers that can be propagated to callback endpoint.
     *
     * @var array<string, true>
     */
    private const array FORWARDED_HEADERS = [
        'x-request-id' => true,
        'x-correlation-id' => true,
        'traceparent' => true,
        'tracestate' => true,
        'baggage' => true,
    ];

    public function __construct(
        protected HttpClientInterface $client,
        protected SerializerInterface $serializer,
        protected RequestCarrier $requestCarrier,
    ) {}

    public function process(RpcRequest $request): void
    {
        try {
            $carrier = $this->requestCarrier->getCarrier();

            $httpRequest = method_exists($carrier, 'getHttpRequest') ? $carrier->getHttpRequest() : null;

            $headerBag = $httpRequest?->headers ?? new HeaderBag();
            $headers = $this->prepareHeaders($headerBag);

            $target = $request->getRpcParams()?->getCallbackObject()->getTarget()
                      ?? throw new RpcAsyncRequestException('Callback target is not set');

            $headers['Content-Type'] = 'application/json';
            $headers['Accept'] = 'application/json';

            $response = $this->client->request('POST', $target, [
                'headers' => $headers,
                'body'    => $this->serializer->serialize($request->getResponseObject(), 'json', context: [
                    AbstractNormalizer::GROUPS => $request->getResponseObject()?->getResponseSignature(),
                ]),
                'timeout' => self::CALLBACK_TIMEOUT,
                'max_duration' => self::CALLBACK_MAX_DURATION,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new RpcAsyncRequestException('Callback endpoint returned HTTP status code: ' . $statusCode);
            }
        } catch (Throwable $e) {
            throw new RpcAsyncRequestException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return array<string, string>
     */
    protected function prepareHeaders(HeaderBag $headerBag): array
    {
        $prepared = [];
        foreach ($headerBag->all() as $name => $values) {
            $normalizedName = strtolower($name);
            if (!array_key_exists($normalizedName, self::FORWARDED_HEADERS)) {
                continue;
            }

            $prepared[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
        }

        return $prepared;
    }
}

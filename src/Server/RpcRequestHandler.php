<?php

namespace Ufo\JsonRpcBundle\Server;

use Laminas\Json\Server\Request as JsonRequest;
use Laminas\Json\Server\Smd;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\Exceptions\RpcJsonParseException;
use Ufo\JsonRpcBundle\Exceptions\RuntimeException;
use Ufo\JsonRpcBundle\Exceptions\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer;

class RpcRequestHandler
{
    protected Request $request;
    
    protected bool $isButchRequest = false;
    
    protected bool $isButch = false;
    
    protected RpcButchRequestObject $butchRequestObject;
    protected RpcRequestObject $requestObject;
    
    public function __construct(
        protected IFacadeRpcServer    $rpcServerFacade,
        protected SerializerInterface $serializer
    )
    {
    }

    public function handle(Request $request, bool $json = false): array|string
    {
        $this->request = $request;
        
        try {
            $result = $this->handlePost();
        } catch (WrongWayException) {
            $result = $this->handleGet();
        }
        return $json ? $this->serializer->serialize($result, 'json') : $result;
    }

    protected function handleGet(): array
    {
        return $this->rpcServerFacade->getServiceMap()->toArray();
    }
    
    protected function handlePost(): array
    {
        if (!$this->isPost()) {
            throw new WrongWayException();
        }
        return $this->checkButchRequest()
            ->createRequestObject()
            ->smartHandle()
        ;
    }
    
    protected function smartHandle(): array
    {
        $result = [];
        if ($this->isButchRequest) {
            $queue = &$this->butchRequestObject->getReadyToHandle();
            foreach ($queue as $key => &$singleRequest) {
                $this->rpcServerFacade->getServer()->newRequest($singleRequest);
                $this->butchRequestObject->addResult($this->provideSingleRequest($singleRequest));
                unset($queue[$key]);
            }

            foreach ($this->butchRequestObject->provideUnprocessedRequests() as $key => $unprocessedRequest) {
                $this->butchRequestObject->addResult($this->provideSingleRequest($unprocessedRequest));
            }

            $result = $this->butchRequestObject->getResults(false);
        } else {
            return $this->provideSingleRequest($this->requestObject);
        }
        return $result;
    }

    protected function provideSingleRequest(RpcRequestObject $singleRequest): array
    {
        $this->rpcServerFacade->getServer()->newRequest($singleRequest);
        $result = $this->rpcServerFacade->handle($singleRequest);

        $context = [
            AbstractNormalizer::GROUPS => [$result->getResponseSignature()],
            RpcErrorNormalizer::RPC_CONTEXT => true,
        ];

        return $this->serializer->normalize($result, context: $context);
    }

    protected function checkButchRequest(): static
    {
        $firstChar = substr($this->request->getContent(), 0, 1);
        if ($firstChar === '[') {
            $this->isButchRequest = true;
        }
        return $this;
    }

    /**
     * @throws RpcJsonParseException
     */
    protected function createRequestObject(): static
    {
        if ($this->isButchRequest) {
            $this->butchRequestObject = RpcButchRequestObject::fromJson($this->request->getContent());
        } else {
            $this->requestObject = RpcRequestObject::fromJson($this->request->getContent());
        }
        return $this;
    }

    public function isGet(): bool
    {
        return $this->checkMethod(Request::METHOD_GET);
    }

    public function isPost(): bool
    {
        return $this->checkMethod(Request::METHOD_POST);
    }

    protected function checkMethod(string $method): bool
    {
        return $method == $this->request->getMethod();
    }
}

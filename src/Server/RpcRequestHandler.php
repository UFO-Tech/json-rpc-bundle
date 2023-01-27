<?php

namespace Ufo\JsonRpcBundle\Server;

use Laminas\Json\Server\Smd;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Ufo\JsonRpcBundle\CliCommand\UfoRpcProcessCommand;
use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\Exceptions\RpcJsonParseException;
use Ufo\JsonRpcBundle\Exceptions\RuntimeException;
use Ufo\JsonRpcBundle\Exceptions\WrongWayException;
use Ufo\JsonRpcBundle\Interfaces\IFacadeRpcServer;
use Ufo\JsonRpcBundle\Serializer\RpcErrorNormalizer;
use Ufo\JsonRpcBundle\Server\Async\RpcAsyncProcessor;

class RpcRequestHandler
{
    protected Request $request;
    
    protected bool $isButchRequest = false;
    
    protected bool $isButch = false;
    
    protected RpcButchRequestObject $butchRequestObject;
    protected RpcRequestObject $requestObject;
    protected RpcAsyncProcessor $asyncProcessor;
    
    public function __construct(
        protected IFacadeRpcServer    $rpcServerFacade,
        protected SerializerInterface $serializer
    )
    {
        $this->asyncProcessor = new RpcAsyncProcessor();
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

    protected function processQueue(array &$queue, ?\Closure $callback)
    {
        foreach ($queue as $key => &$singleRequest) {

            $this->asyncProcessor->createProcesses(
                $singleRequest,
                $this->rpcServerFacade->getSecurity()->getToken(),
                timeout: 30
            );
            unset($queue[$key]);
        }

        $this->asyncProcessor->process($callback);
    }

    protected function callbackAsyncResponse(): \Closure
    {
        $self = $this;
         return function (string $output) use ($self) {
             $butchRequestObject = $self->butchRequestObject;

            // todo use serializer  $self->serializer;
            $butchRequestObject->addResult(json_decode($output, true));

            if ($butchRequestObject->getReadyToHandle()) {
                $self->processQueue($butchRequestObject->getReadyToHandle(), $self->callbackAsyncResponse());
            }
        };
}
    protected function smartHandle(): array
    {
        $butchRequestObject = $this->butchRequestObject;
        if ($this->isButchRequest) {
            $this->processQueue(
                $butchRequestObject->getReadyToHandle(),
                $this->callbackAsyncResponse()
            );

            foreach ($butchRequestObject->provideUnprocessedRequests() as $key => $unprocessedRequest) {
                $butchRequestObject->addResult($this->provideSingleRequest($unprocessedRequest));

            }

            $result = $butchRequestObject->getResults(false);
        } else {
            $result = $this->provideSingleRequest($this->requestObject);
        }
        return $result;
    }

    public function provideSingleRequest(RpcRequestObject $singleRequest): array
    {
        $result = $this->provideSingleRequestObjectResponse($singleRequest);
        $context = [
            AbstractNormalizer::GROUPS => [$result->getResponseSignature()],
            RpcErrorNormalizer::RPC_CONTEXT => true,
        ];
        return $this->serializer->normalize($result, context: $context);
    }

    public function provideSingleRequestObjectResponse(RpcRequestObject $singleRequest): RpcResponseObject
    {
        $this->rpcServerFacade->getServer()->newRequest($singleRequest);
        $result = $this->rpcServerFacade->handle($singleRequest);

        return $result;
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

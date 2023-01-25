<?php

namespace Ufo\JsonRpcBundle\Server;


use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\Exceptions\RpcJsonParseException;

class RpcButchRequestObject
{
    /**
     * @var RpcRequestObject[]
     */
    private array $requestObjectCollection = [];
    /**
     * @var RpcRequestObject[]
     */
    private array $readyToHandle = [];
    /**
     * @var RpcRequestObject[]
     */
    private array $waitForOtherResponse = [];

    private array $results = [];

    public static function fromJson(string $json): static
    {
        try {
            $data = json_decode($json, true);
            $collection = new static();
            foreach ($data as $requestArray) {
                $collection->addRequestObject(RpcRequestObject::fromArray($requestArray));
            }
            return $collection;

        } catch (\TypeError $e) {
            throw new RpcJsonParseException('Invalid json data', previous: $e);
        }
    }

    /**
     * @return RpcRequestObject[]
     */
    public function getCollection(): array
    {
        return $this->requestObjectCollection;
    }

    /**
     * @return RpcRequestObject[]
     */
    public function &getReadyToHandle(): array
    {
        return $this->readyToHandle;
    }

    /**
     * @param RpcRequestObject $requestObject
     * @return $this
     */
    public function addRequestObject(RpcRequestObject $requestObject): static
    {
        $this->requestObjectCollection[$requestObject->getId()] = $requestObject;

        $this->addToQueue($requestObject);
        return $this;
    }

    protected function addToQueue(RpcRequestObject $requestObject): static
    {
        $collectionName = $requestObject->hasRequire() ? 'waitForOtherResponse' : 'readyToHandle';
        $this->{$collectionName}[$requestObject->getId()] = $requestObject;
        return $this;
    }

    protected function changeQueue(RpcRequestObject $requestObject): static
    {
        unset($this->readyToHandle[$requestObject->getId()]);
        unset($this->waitForOtherResponse[$requestObject->getId()]);
        $this->addToQueue($requestObject);
        return $this;
    }

    public function addResult(array $result): static
    {
        $this->results[$result['id']] = $result;
        $this->refreshQueue($result['id']);
        return $this;
    }

    protected function refreshQueue(string|int $id)
    {
        foreach ($this->waitForOtherResponse as $queueId => $requestObject) {
            if ($requestObject->checkRequireId($id)) {
                foreach ($requestObject->getRequire() as $paramName => $rrrpfr) {
                    try {
                        if (!isset($this->results[$id]['result'])) {
                            throw new RpcBadRequestException(
                                sprintf(
                                    'The parent\'s request "%s" returned the error. I can\'t substitute values in the current request.',
                                    $id, $paramName
                                )
                            );
                        }
                        if (!isset($this->results[$id]['result'][$rrrpfr->getResponseFieldName()])) {
                            throw new RpcBadRequestException(
                                sprintf(
                                    'The parent request "%s" does not have a "%s" field in the response. I can\'t substitute value "%s" in the current request.',
                                    $id, $rrrpfr->getResponseFieldName(), $paramName
                                )
                            );
                        }
                        if ($rrrpfr->getResponseId() != $id) {
                            continue;
                        }
                        
                        $newValue = $this->results[$id]['result'][$rrrpfr->getResponseFieldName()];
                        $requestObject->replaceRequestParam($paramName, $newValue);
                        $this->changeQueue($requestObject);
                    } catch (\Throwable $e) {
                        $requestObject->setError($e);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getResults($withKeys = true): array
    {
        return $withKeys ? $this->results : array_values($this->results);
    }

    public function getUnprocessedRequests(): array
    {
        return $this->waitForOtherResponse;
    }

    /**
     * @return RpcRequestObject[]
     */
    public function provideUnprocessedRequests(): array
    {
        if (count($this->waitForOtherResponse) > 0) {
            array_walk($this->waitForOtherResponse, function ($unprocessedRequest, $key) {
                /**
                 * @var RpcRequestObject $unprocessedRequest
                 */
                if (!$unprocessedRequest->hasError()) {
                    $unprocessedRequest->setError(
                        new RpcBadRequestException(
                            sprintf(
                                'The parent\'s request "%s" is not found. I can\'t substitute values in the current request.',
                                $unprocessedRequest->getCurrentRequireId()
                            )
                        )
                    );
                }
            });
        }
        return $this->waitForOtherResponse;
    }

}

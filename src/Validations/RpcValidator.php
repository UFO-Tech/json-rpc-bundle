<?php

namespace Ufo\JsonRpcBundle\Validations;


use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Ufo\JsonRpcBundle\Interfaces\IRpcValidator;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\JsonRpcBundle\Exceptions\ConstraintsImposedException;
use function array_map;
use function count;
use function json_encode;

class RpcValidator implements IRpcValidator
{
    protected int $violationCount = 0;
    /**
     * @var ConstraintViolationListInterface[]
     */
    protected array $violations;

    public function __construct(protected ValidatorInterface $validator) {}

    /**
     * @throws \ReflectionException|ConstraintsImposedException
     */
    public function validateMethodParams(
        object $procedureObject,
        string $procedureMethod,
        array $params
    ): void {
        $refMethod = new ReflectionMethod($procedureObject, $procedureMethod);
        $paramRefs = $refMethod->getParameters();
        foreach ($paramRefs as $paramRef) {
            $this->validateParam($paramRef, $params[$paramRef->getName()] ?? null);
        }
        if ($this->violationCount > 0) {
            $errors = [];
            foreach ($this->violations as $paramName => $violations) {
                array_map(function (ConstraintViolationInterface $v) use (&$errors, $paramName) {
                    $errors[$paramName][] = $v->getMessage();
                },
                    (array)$violations->getIterator());
            }
            throw new ConstraintsImposedException("Invalid Data for call method: {$procedureMethod}", $errors);
        }
    }

    protected function validateParam(ReflectionParameter $paramRef, mixed $value): void
    {
        try {
            $attribute = $paramRef->getAttributes(Assertions::class)[0];
            $assertions = $attribute->newInstance()->assertions;
            $violations = $this->validator->validate($value, $assertions);
            if (count($violations) > 0) {
                $this->violations[$paramRef->getName()] = $violations;
                $this->violationCount += count($violations);
            }
        } catch (\Throwable) {
        }
    }
}
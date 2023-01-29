<?php

namespace Ufo\JsonRpcBundle\RpcCallback\Validator;

use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Psr18NetworkException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\UrlValidator;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class RealUrlValidator extends UrlValidator
{

    public function __construct()
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AsertRealUrl) {
            throw new UnexpectedTypeException($constraint, AsertRealUrl::class);
        }

        parent::validate($value, $constraint);

        if (null === $value || '' === $value) {
            return;
        }

        try {
            $error = false;
            $client = new Psr18Client();
            $client->withOptions([
                'timeout' => 1,
                'max_duration' => 1
            ]);
            $request = $client->createRequest('OPTIONS', $value);
            $response = $client->sendRequest($request);
        } catch (\Throwable $e) {
            $error = true;
        }

        if ($error) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}

<?php

namespace Ufo\JsonRpcBundle\RpcCallback;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Ufo\JsonRpcBundle\Exceptions\RpcBadRequestException;
use Ufo\JsonRpcBundle\RpcCallback\CallbackUrlValidator;
use Ufo\JsonRpcBundle\RpcCallback\Validator\AsertRealUrl;

class CallbackObject
{
    public function __construct(
        protected string $target,
    )
    {
        $this->validate();
    }

    protected function validate()
    {
        $validator = Validation::createValidator();
        $errors = $validator->validate($this->target, static::getValidationConstrainForBuild());

        if ($errors->count() > 0) {
            $message = $errors[0]->getPropertyPath() . ': ' . $errors[0]->getMessage();
        }
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    public function __toString(): string
    {
        return $this->getTarget();
    }

    /**
     * @return array Validate ruls for create Request from array
     */
    public static function getValidationConstrainForBuild(): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Url(),
            new Assert\Url(
                message: 'Must have a protocol',
                relativeProtocol: true,
            ),
            new Assert\Url(
                message: 'Invalid protocol',
                protocols: ['https'],
            ),
            new AsertRealUrl(message: 'Callback does not respond'),
        ];
    }
}

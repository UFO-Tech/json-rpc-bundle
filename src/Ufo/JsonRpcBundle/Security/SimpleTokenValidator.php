<?php
/**
 * Created by PhpStorm.
 * User: ashterix
 * Date: 30.04.17
 * Time: 9:04
 */

namespace Ufo\JsonRpcBundle\Security;


use Ufo\JsonRpcBundle\Exceptions\InvalidTokenException;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;

class SimpleTokenValidator implements ITokenValidator
{

    /**
     * @var array
     */
    protected $clientsTokens = [];

    /**
     * SimpleTokenValidator constructor.
     * @param array $clientsTokens
     */
    public function __construct(array $clientsTokens)
    {
        $this->clientsTokens = $clientsTokens;
    }

    /**
     * @param string $token
     * @return bool
     * @throws InvalidTokenException
     */
    public function isValid($token)
    {
        $valid = in_array($token, $this->clientsTokens);
        if (false == $valid) {
            throw new InvalidTokenException();
        }
        return $valid;
    }

    /**
     * @return array
     */
    public function getClientsTokens()
    {
        return $this->clientsTokens;
    }

}
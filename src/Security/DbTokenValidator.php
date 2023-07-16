<?php

namespace Ufo\JsonRpcBundle\Security;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\SecurityBundle\Security;
use Ufo\JsonRpcBundle\Security\Interfaces\ITokenValidator;
use Ufo\RpcError\RpcInvalidTokenException;

class DbTokenValidator implements ITokenValidator
{
    protected ObjectManager $em;

    public function __construct(
        protected ManagerRegistry $doctrine,
        protected Security $security
    )
    {
        $this->em = $this->doctrine->getManager();
    }

    /**
     * @inheritDoc
     */
    public function isValid(string $token): bool
    {
        try {
            $user = $this->userService->getUserByToken($token);
            $this->security->login($user);
            return true;
        } catch (\Throwable) {
            throw new RpcInvalidTokenException();
        }
    }
}

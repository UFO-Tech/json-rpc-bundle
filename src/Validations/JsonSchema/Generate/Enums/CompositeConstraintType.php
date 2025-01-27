<?php

namespace Ufo\JsonRpcBundle\Validations\JsonSchema\Generate\Enums;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Optional;

enum CompositeConstraintType: string
{
    case ALL = All::class;
    case OPTIONAL = Optional::class;
    case COLLECTION = Collection::class;

    const array TYPES = [
        All::class => self::ALL,
        Optional::class => self::OPTIONAL,
        Collection::class => self::COLLECTION,
    ];

    public static function fromConstraint(Constraint $constraint): ?self
    {
        return self::TYPES[$constraint::class] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Tests\Fixtures\Procedures;

use Symfony\Component\Validator\Constraints as Assert;
use Ufo\JsonRpcBundle\ApiMethod\Interfaces\IRpcService;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\Color;
use Ufo\JsonRpcBundle\Tests\Fixtures\Dto\ShippingMethods;
use Ufo\RpcObject\RPC;

/** Choice у параметрах: зі списком, з callback на enum і з callback на звичайний клас. */
#[RPC\Info('choices')]
class ChoiceDocProcedure implements IRpcService
{
    public function listed(
        #[RPC\Assertions([new Assert\Choice(choices: ['a', 'b'])])]
        string $letter,
    ): bool {
        return true;
    }

    public function fromEnum(
        #[RPC\Assertions([new Assert\Choice(callback: [Color::class, 'cases'])])]
        string $color,
    ): bool {
        return true;
    }

    public function fromClass(
        #[RPC\Assertions([new Assert\NotBlank(), new Assert\Choice(callback: [ShippingMethods::class, 'all'])])]
        string $shipping,
    ): bool {
        return true;
    }

    public function stringCallback(
        #[RPC\Assertions([new Assert\Choice(callback: 'ufo_choice_doc_shippings')])]
        string $shipping,
    ): bool {
        return true;
    }

    /** @param string[] $shippings */
    public function nestedFromClass(
        #[RPC\Assertions([new Assert\All([new Assert\Choice(callback: [ShippingMethods::class, 'all'])])])]
        array $shippings,
    ): bool {
        return true;
    }
}

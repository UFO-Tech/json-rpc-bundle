<?php

namespace Ufo\JsonRpcBundle\SoupUi\Node\Interfaces;


interface ICanTransformToArray
{
    /**
     * @return array
     */
    public function toArray(): array;
}
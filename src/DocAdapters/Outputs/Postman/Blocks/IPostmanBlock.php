<?php

namespace Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks;

interface IPostmanBlock
{
    public function toArray(): array;
}
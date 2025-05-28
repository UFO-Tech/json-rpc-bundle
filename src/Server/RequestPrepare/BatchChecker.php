<?php

namespace Ufo\JsonRpcBundle\Server\RequestPrepare;

use function substr;
use function trim;

trait BatchChecker
{
    protected function checkBatchRequest(string $content): bool
    {
        $firstChar = substr(trim($content), 0, 1);
        return ($firstChar === '[');
    }
}
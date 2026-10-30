<?php

namespace Ufo\JsonRpcBundle\Server\ServiceMap;

use Ufo\RpcError\RpcInternalException;

use function count;
use function implode;
use function ksort;
use function ltrim;
use function sort;
use function sprintf;
use function strrpos;
use function substr;

/** Імена DTO та enum у документі API — короткі назви класів, тож вони мають бути унікальними. */
class SchemaNames
{
    /**
     * @param iterable<string> $fqcns
     * @throws RpcInternalException якщо кілька класів мають однакову коротку назву
     */
    public static function assertUnique(iterable $fqcns): void
    {
        $groups = [];
        foreach ($fqcns as $fqcn) {
            $fqcn = ltrim($fqcn, '\\');
            $groups[static::shortName($fqcn)][$fqcn] = $fqcn;
        }
        ksort($groups);

        $clashes = [];
        foreach ($groups as $short => $group) {
            if (count($group) > 1) {
                sort($group);
                $clashes[] = sprintf('"%s" (%s)', $short, implode(', ', $group));
            }
        }

        if ($clashes) {
            throw new RpcInternalException(sprintf(
                'API schema names must be unique among DTO and enum classes, duplicated: %s. Rename the classes so that their short names are unique within the API',
                implode('; ', $clashes),
            ));
        }
    }

    public static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}

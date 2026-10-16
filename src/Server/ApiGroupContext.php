<?php

declare(strict_types=1);

namespace Ufo\JsonRpcBundle\Server;

use InvalidArgumentException;
use Symfony\Contracts\Service\ResetInterface;
use Ufo\RpcObject\RPC\APIGroups;

class ApiGroupContext implements ResetInterface
{
    public const string ROUTE_GROUP = 'api_group';
    public const string ROUTE_NAME_PREFIX = '_rpc_name_prefix';

    private string $group = APIGroups::DEFAULT_GROUP;
    private ?string $routeNamePrefix = null;

    public static function validateGroup(string $group): string
    {
        if (trim($group) === '') {
            throw new InvalidArgumentException('RPC API group must be a non-empty string.');
        }
        return $group;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function isDefault(): bool
    {
        return $this->group === APIGroups::DEFAULT_GROUP;
    }

    public function setGroup(string $group, ?string $routeNamePrefix = null): void
    {
        $this->group = self::validateGroup($group);
        $this->routeNamePrefix = $routeNamePrefix;
    }

    public function getRouteName(string $baseName): string
    {
        return ($this->routeNamePrefix ?? $this->group.'_').$baseName;
    }

    public function reset(): void
    {
        $this->group = APIGroups::DEFAULT_GROUP;
        $this->routeNamePrefix = null;
    }
}

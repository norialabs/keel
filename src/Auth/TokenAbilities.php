<?php

declare(strict_types=1);

namespace Noria\Keel\Auth;

use Noria\Keel\Contracts\PermissionAction;
use Noria\Keel\Contracts\PermissionResource;
use Noria\Keel\Db\KeyType;
use Noria\Keel\Rbac\Catalog;

final class TokenAbilities
{
    public const ALL = '*';

    public const WORKSPACE_PREFIX = 'workspace:';

    private function __construct() {}

    /**
     * @param  array<array-key, mixed>  $abilities
     */
    public static function workspaceIn(array $abilities): ?string
    {
        $found = [];

        foreach ($abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, self::WORKSPACE_PREFIX)) {
                $found[] = substr($ability, strlen(self::WORKSPACE_PREFIX));
            }
        }

        $type = KeyType::forTenants();

        return count($found) === 1 && $type->accepts($found[0]) ? $type->normalise($found[0]) : null;
    }

    public static function forWorkspace(string $workspaceId): string
    {
        return self::WORKSPACE_PREFIX.$workspaceId;
    }

    public static function permission(PermissionResource $resource, PermissionAction $action): string
    {
        return $resource->value.':'.$action->value;
    }

    public static function isValid(string $ability): bool
    {
        if ($ability === self::ALL) {
            return true;
        }

        if (str_starts_with($ability, self::WORKSPACE_PREFIX)) {
            return KeyType::forTenants()->accepts(substr($ability, strlen(self::WORKSPACE_PREFIX)));
        }

        [$resource, $action] = array_pad(explode(':', $ability, 2), 2, null);

        $parsedResource = Catalog::resource((string) $resource);
        $parsedAction = Catalog::action((string) $action);

        return $parsedResource !== null && $parsedAction !== null && $parsedResource->supports($parsedAction);
    }
}

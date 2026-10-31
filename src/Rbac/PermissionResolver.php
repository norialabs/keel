<?php

declare(strict_types=1);

namespace Noria\Keel\Rbac;

use Illuminate\Contracts\Auth\Authenticatable;
use Noria\Keel\Contracts\PermissionAction;
use Noria\Keel\Contracts\PermissionCeiling;
use Noria\Keel\Contracts\PermissionResource;
use Noria\Keel\Contracts\PrincipalResolver;
use Noria\Keel\Contracts\RoleRepository;

class PermissionResolver
{
    /** @var array<string, Permissions> */
    private array $resolved = [];

    public function __construct(
        private PrincipalResolver $principals,
        private RoleRepository $roles,
        private ?PermissionCeiling $ceiling = null,
    ) {}

    public function allows(Authenticatable $user, PermissionResource $resource, PermissionAction $action): bool
    {
        return $this->permissionsFor($user)->has($resource, $action);
    }

    public function permissionsFor(Authenticatable $user): Permissions
    {
        $principal = $this->principals->for($user);

        if ($principal === null) {
            return Permissions::none();
        }

        $scope = Catalog::scopeKey($principal->scope());
        $key = $scope.'|'.($principal->contextId() ?? '').'|'.implode(',', $principal->roleSlugs());

        return $this->resolved[$key] ??= $this->roles
            ->permissionsFor($scope, $principal->roleSlugs())
            ->withinCeiling($this->ceiling?->for($user));
    }

    public function flush(): void
    {
        $this->resolved = [];
    }
}

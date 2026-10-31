<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

use Noria\Keel\Rbac\Permissions;

interface RoleRepository
{
    /** @param list<string> $slugs */
    public function permissionsFor(string $scope, array $slugs): Permissions;
}

<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Noria\Keel\Rbac\Permissions;

interface PermissionCeiling
{
    public function for(Authenticatable $user): ?Permissions;
}

<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Noria\Keel\Contracts\PermissionCeiling;
use Noria\Keel\Rbac\Permissions;

class StubCeiling implements PermissionCeiling
{
    public static ?Permissions $ceiling = null;

    public function for(Authenticatable $user): ?Permissions
    {
        return self::$ceiling;
    }
}

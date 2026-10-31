<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

use BackedEnum;

interface ScopedPermissionResource extends PermissionResource
{
    public function scope(): string|BackedEnum;
}

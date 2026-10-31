<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Tenancy\Tenancy;
use Noria\Keel\Tenancy\TenantJob;

class CountWidgets extends TenantJob
{
    public static ?string $ranInside = null;

    public function work(Tenancy $tenancy): void
    {
        self::$ranInside = $tenancy->id();
    }
}

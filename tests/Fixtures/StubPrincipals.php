<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Noria\Keel\Contracts\Principal;
use Noria\Keel\Contracts\PrincipalResolver;

class StubPrincipals implements PrincipalResolver
{
    public static ?Principal $principal = null;

    public static int $calls = 0;

    public function for(Authenticatable $user): ?Principal
    {
        self::$calls++;

        return self::$principal;
    }
}

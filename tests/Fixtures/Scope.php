<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

enum Scope: string
{
    case Tenant = 'tenant';
    case Platform = 'platform';
}

<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Audit\AuditLog;

class HostAuditLog extends AuditLog
{
    public function label(): string
    {
        return 'host';
    }
}

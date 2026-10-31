<?php

declare(strict_types=1);

namespace Noria\Keel\Events;

use Noria\Keel\Audit\AuditLog;

final readonly class AuditRecorded
{
    public function __construct(public AuditLog $entry) {}
}

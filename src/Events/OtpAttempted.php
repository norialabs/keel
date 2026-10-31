<?php

declare(strict_types=1);

namespace Noria\Keel\Events;

use Noria\Keel\Auth\OtpOutcome;
use Noria\Keel\Identity\Destination;

final readonly class OtpAttempted
{
    public function __construct(
        public Destination $destination,
        public OtpOutcome $outcome,
    ) {}
}

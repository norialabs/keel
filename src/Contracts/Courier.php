<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

use Noria\Keel\Identity\Channel;
use Noria\Keel\Identity\Destination;

interface Courier
{
    /** @param array<string, mixed> $context */
    public function deliver(Destination $to, Channel $channel, string $kind, array $context): void;
}

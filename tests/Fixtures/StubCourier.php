<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Contracts\Courier;
use Noria\Keel\Identity\Channel;
use Noria\Keel\Identity\Destination;

class StubCourier implements Courier
{
    /** @var list<array{to: string, channel: string, kind: string, context: array<string, mixed>}> */
    public static array $sent = [];

    public function deliver(Destination $to, Channel $channel, string $kind, array $context): void
    {
        self::$sent[] = ['to' => $to->value, 'channel' => $channel->value, 'kind' => $kind, 'context' => $context];
    }
}

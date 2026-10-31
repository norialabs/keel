<?php

declare(strict_types=1);

namespace Noria\Keel\Events;

use Noria\Keel\Auth\OtpChallenge;
use Noria\Keel\Identity\Channel;
use Noria\Keel\Identity\Destination;

final readonly class OtpIssued
{
    public function __construct(
        public OtpChallenge $challenge,
        public Destination $destination,
        public Channel $channel,
    ) {}
}

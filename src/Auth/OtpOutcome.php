<?php

declare(strict_types=1);

namespace Noria\Keel\Auth;

enum OtpOutcome: string
{
    case Verified = 'verified';
    case NoChallenge = 'no_challenge';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Incorrect = 'incorrect';

    public function verified(): bool
    {
        return $this === self::Verified;
    }
}

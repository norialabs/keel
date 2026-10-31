<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Auth\OtpChallenge;

class HostOtpChallenge extends OtpChallenge
{
    public function label(): string
    {
        return 'host';
    }
}

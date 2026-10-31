<?php

declare(strict_types=1);

namespace Noria\Keel\Auth;

use RuntimeException;

class OtpThrottled extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter)
    {
        parent::__construct($message);
    }
}

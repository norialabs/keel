<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

interface LogContext
{
    /** @return array<string, mixed> */
    public function capture(): array;
}

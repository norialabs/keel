<?php

declare(strict_types=1);

namespace Noria\Keel\Auth;

use RuntimeException;

class UnscopedToken extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A personal access token must name exactly one workspace.');
    }
}

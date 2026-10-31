<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

use Illuminate\Http\Request;

interface WorkspaceResolver
{
    public function resolve(Request $request): ?string;
}

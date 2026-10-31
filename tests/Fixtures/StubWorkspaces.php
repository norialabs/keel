<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Illuminate\Http\Request;
use Noria\Keel\Contracts\WorkspaceResolver;

class StubWorkspaces implements WorkspaceResolver
{
    public static ?string $workspaceId = null;

    public function resolve(Request $request): ?string
    {
        return self::$workspaceId;
    }
}

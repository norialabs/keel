<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Contracts\PermissionAction;

enum Action: string implements PermissionAction
{
    case View = 'view';
    case Create = 'create';
    case Delete = 'delete';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Contracts\PermissionAction;
use Noria\Keel\Contracts\ScopedPermissionResource;

enum ScopedByEnum: string implements ScopedPermissionResource
{
    case Invoice = 'invoice';
    case Report = 'report';
    case Ledger = 'ledger';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function scope(): Scope
    {
        return $this === self::Ledger ? Scope::Platform : Scope::Tenant;
    }

    public function actions(): array
    {
        return $this === self::Report ? [Action::View] : [Action::View, Action::Create, Action::Delete];
    }

    public function supports(PermissionAction $action): bool
    {
        return in_array($action, $this->actions(), true);
    }
}

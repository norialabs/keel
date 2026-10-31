<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

interface PermissionResource extends \BackedEnum
{
    public function label(): string;

    /** @return list<PermissionAction> the verbs this resource admits */
    public function actions(): array;

    public function supports(PermissionAction $action): bool;
}

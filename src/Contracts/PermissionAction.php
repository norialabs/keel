<?php

declare(strict_types=1);

namespace Noria\Keel\Contracts;

interface PermissionAction extends \BackedEnum
{
    public function label(): string;
}

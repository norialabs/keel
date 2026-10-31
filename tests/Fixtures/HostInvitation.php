<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Noria\Keel\Invitations\Invitation;

class HostInvitation extends Invitation
{
    public function label(): string
    {
        return 'host';
    }
}

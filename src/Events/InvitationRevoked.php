<?php

declare(strict_types=1);

namespace Noria\Keel\Events;

use Noria\Keel\Invitations\Invitation;

final readonly class InvitationRevoked
{
    public function __construct(public Invitation $invitation) {}
}

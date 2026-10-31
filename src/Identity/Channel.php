<?php

declare(strict_types=1);

namespace Noria\Keel\Identity;

enum Channel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function needsEmail(): bool
    {
        return $this === self::Email;
    }
}

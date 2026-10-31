<?php

declare(strict_types=1);

namespace Noria\Keel\Console;

use Illuminate\Console\Command;
use Noria\Keel\Auth\Otp;

class PruneOtpCommand extends Command
{
    protected $signature = 'keel:prune-otp {--days=7 : How long an expired code is kept}';

    protected $description = 'Remove sign-in codes nobody will use again';

    public function handle(Otp $otp): int
    {
        $removed = $otp->prune((int) $this->option('days'));

        $this->components->info($removed.' expired sign-in codes removed.');

        return self::SUCCESS;
    }
}

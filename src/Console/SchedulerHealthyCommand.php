<?php

declare(strict_types=1);

namespace Noria\Keel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchedulerHealthyCommand extends Command
{
    protected $signature = 'keel:scheduler-healthy';

    protected $description = 'Exit non-zero when the scheduler has stopped firing';

    public const KEY = 'keel:scheduler:heartbeat';

    public function handle(): int
    {
        $beat = Cache::get(self::KEY);

        if ($beat === null) {
            $this->components->error('The scheduler has not checked in.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

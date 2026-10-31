<?php

declare(strict_types=1);

namespace Noria\Keel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Noria\Keel\Keel;

class SchedulerHeartbeatCommand extends Command
{
    protected $signature = 'keel:scheduler-heartbeat';

    protected $description = 'Record that the scheduler is still firing';

    public function handle(): int
    {
        Keel::cache()->put(
            SchedulerHealthyCommand::KEY,
            now()->toIso8601String(),
            Config::integer('keel.scheduler.heartbeat_ttl', 300),
        );

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

use Noria\Keel\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/** @param  array<string, mixed>  $settings */
function remigrate(array $settings): void
{
    $migration = require __DIR__.'/../database/migrations/2026_09_19_000001_create_keel_tables.php';
    $migration->down();
    config($settings);
    $migration->up();
}

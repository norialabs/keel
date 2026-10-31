<?php

declare(strict_types=1);

namespace Noria\Keel\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noria\Keel\Contracts\PermissionCeiling;
use Noria\Keel\Contracts\PrincipalResolver;
use Noria\Keel\Contracts\RoleRepository;
use Noria\Keel\Contracts\WorkspaceResolver;
use Noria\Keel\Keel;
use Noria\Keel\KeelServiceProvider;
use Noria\Keel\Tests\Fixtures\Action;
use Noria\Keel\Tests\Fixtures\Resource;
use Noria\Keel\Tests\Fixtures\StubCeiling;
use Noria\Keel\Tests\Fixtures\StubPrincipals;
use Noria\Keel\Tests\Fixtures\StubRoles;
use Noria\Keel\Tests\Fixtures\StubWorkspaces;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Keel::forgetModels();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [KeelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        if (($url = env('KEEL_TEST_PG')) !== null) {
            $app['config']->set('database.connections.keel_pg', [
                'driver' => 'pgsql',
                'url' => $url,
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
                'timezone' => 'UTC',
            ]);
            $app['config']->set('database.default', 'keel_pg');

            if (($admin = env('KEEL_TEST_PG_ADMIN')) !== null) {
                $app['config']->set('database.connections.keel_pg_admin', [
                    'driver' => 'pgsql',
                    'url' => $admin,
                    'charset' => 'utf8',
                    'prefix' => '',
                    'search_path' => 'public',
                    'sslmode' => 'prefer',
                    'timezone' => 'UTC',
                ]);
            }

            if (($superuser = env('KEEL_TEST_PG_SUPERUSER')) !== null) {
                $app['config']->set('database.connections.keel_pg_superuser', [
                    'driver' => 'pgsql',
                    'url' => $superuser,
                    'charset' => 'utf8',
                    'prefix' => '',
                    'search_path' => 'public',
                    'sslmode' => 'prefer',
                    'timezone' => 'UTC',
                ]);
            }
        } else {
            $app['config']->set('database.default', 'testing');

            $app['config']->set('keel.tenancy.enabled', false);
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('keel.identity.hash_key', 'a-test-hash-key');
        $app['config']->set('keel.rbac.resources', Resource::class);
        $app['config']->set('keel.rbac.actions', Action::class);

        $app->bind(WorkspaceResolver::class, StubWorkspaces::class);
        $app->bind(PrincipalResolver::class, StubPrincipals::class);
        $app->bind(RoleRepository::class, StubRoles::class);
        $app->bind(PermissionCeiling::class, StubCeiling::class);
    }
}

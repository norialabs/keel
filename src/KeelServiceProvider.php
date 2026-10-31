<?php

declare(strict_types=1);

namespace Noria\Keel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Noria\Keel\Audit\AuditRecorder;
use Noria\Keel\Audit\RequestContext;
use Noria\Keel\Auth\Otp;
use Noria\Keel\Auth\SocialState;
use Noria\Keel\Console\BackupCommand;
use Noria\Keel\Console\BuildErrorPagesCommand;
use Noria\Keel\Console\PruneOtpCommand;
use Noria\Keel\Console\RebuildCommand;
use Noria\Keel\Console\RestoreCommand;
use Noria\Keel\Console\SchedulerHealthyCommand;
use Noria\Keel\Console\SchedulerHeartbeatCommand;
use Noria\Keel\Console\TenancyCheckCommand;
use Noria\Keel\Contracts\Courier;
use Noria\Keel\Contracts\PermissionAction;
use Noria\Keel\Contracts\PermissionCeiling;
use Noria\Keel\Contracts\PermissionResource;
use Noria\Keel\Contracts\PrincipalResolver;
use Noria\Keel\Contracts\RoleRepository;
use Noria\Keel\Db\Backup;
use Noria\Keel\Db\DumperFactory;
use Noria\Keel\Db\Rebuild;
use Noria\Keel\Db\Restore;
use Noria\Keel\Identity\KeyedHash;
use Noria\Keel\Invitations\Invitations;
use Noria\Keel\Rbac\PermissionResolver;
use Noria\Keel\Tenancy\Tenancy;

class KeelServiceProvider extends ServiceProvider
{
    public const GATE = 'workspace-permission';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/keel.php', 'keel');

        $this->app->scoped(Tenancy::class);
        $this->app->scoped(PermissionResolver::class, fn (Application $app): PermissionResolver => new PermissionResolver(
            $app->make(PrincipalResolver::class),
            $app->make(RoleRepository::class),
            $app->bound(PermissionCeiling::class) ? $app->make(PermissionCeiling::class) : null,
        ));

        $this->app->scoped(RequestContext::class, fn (Application $app): RequestContext => new RequestContext(
            $app->bound(Request::class) ? $app->make(Request::class) : null,
        ));

        $this->app->scoped(AuditRecorder::class);
        $this->app->singleton(KeyedHash::class, fn (): KeyedHash => new KeyedHash);
        $this->app->singleton(Otp::class);
        $this->app->scoped(Invitations::class, fn (Application $app): Invitations => new Invitations(
            $app->make(KeyedHash::class),
            $app->make(Tenancy::class),
            $app->bound(Courier::class) ? $app->make(Courier::class) : null,
        ));
        $this->app->singleton(SocialState::class);
        $this->app->singleton(DumperFactory::class);
        $this->app->singleton(Backup::class);
        $this->app->singleton(Restore::class);
        $this->app->singleton(Rebuild::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BackupCommand::class,
                RestoreCommand::class,
                TenancyCheckCommand::class,
                PruneOtpCommand::class,
                SchedulerHealthyCommand::class,
                SchedulerHeartbeatCommand::class,
                RebuildCommand::class,
                BuildErrorPagesCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/keel.php' => config_path('keel.php'),
            ], 'keel-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'keel-migrations');
        }

        if (Config::boolean('keel.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerGate();
    }

    private function registerGate(): void
    {
        if (! $this->app->bound(PrincipalResolver::class) || ! $this->app->bound(RoleRepository::class)) {
            return;
        }

        Gate::define(self::GATE, fn (
            Authenticatable $user,
            PermissionResource $resource,
            PermissionAction $action,
        ): bool => $this->app->make(PermissionResolver::class)->allows($user, $resource, $action));
    }
}

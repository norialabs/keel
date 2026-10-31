# Upgrading from Platform

[Documentation index](../README.md#documentation)

The package is now named `norialabs/keel`, with the namespace `Noria\Keel\`. Updating an
application requires changes to its Composer dependency and class references.

## Dependency and namespaces

Replace `norialabs/platform` with `norialabs/keel` in the application's Composer requirements,
then update dependencies using Composer.

| Previous reference | Keel reference |
|---|---|
| `NoriaLabs\Platform\` | `Noria\Keel\` |
| `Noria\Platform\` | `Noria\Keel\` |
| `Platform` entry-point class | `Noria\Keel\Keel` |
| `PlatformServiceProvider` | `Noria\Keel\KeelServiceProvider` |

Update imports, fully qualified class names, manually registered providers, model subclasses,
container bindings, and tests. Keel does not provide aliases for the previous namespace.

Check persisted class references, including queued jobs and serialized payloads, before switching
versions. Drain or migrate affected data using an application-specific rollout plan. Rebuild
Laravel's cached configuration and other deployment caches after updating.

## Configuration

Every `noria` prefix is now `keel`:

| Previous | Keel |
|---|---|
| `config/noria.php`, `config('noria.*')` | `config/keel.php`, `config('keel.*')` |
| `NORIA_*` environment variables | `KEEL_*` |
| `noria:*` commands | `keel:*` |
| `noria-config`, `noria-migrations` publication tags | `keel-config`, `keel-migrations` |
| `keel.tenancy.noria_write_guc`, `NORIA_WRITE_GUC` | `keel.tenancy.platform_write_guc`, `KEEL_PLATFORM_WRITE_GUC` |

Rename a published `config/noria.php` to `config/keel.php`, change its keys, and rename the
variables in every environment before deploying. Unrenamed variables are ignored silently and the
defaults apply. Update scheduler entries and deployment scripts that call `noria:*` commands.

Cached scheduler heartbeats and social sign-in state use new keys. The first heartbeat after
deployment restores `keel:scheduler-healthy`. Social sign-ins that are in progress during the
deployment must start again.

## Database

Table names, the tenant column, and the `app.workspace_id`, `app.user_id`, `app.staff_read`, and
`app.invitation_token` settings are unchanged.

The application-wide write setting is now `app.platform_write`, and
`Rls::allowPlatformWrite()` names its policies `<table>_platform_write`. Policies created before
the upgrade still test `app.noria_write`, so recreate them:

```sql
drop policy <table>_noria_write on <table>;
```

Then call `Rls::allowPlatformWrite('<table>')` again, in a new migration. To keep the old setting
instead, set `KEEL_PLATFORM_WRITE_GUC=app.noria_write`.

The package migration is now `2026_09_19_000001_create_keel_tables`. In a database where the
previous migration already ran, record it under the new name so that it does not run again:

```sql
update migrations
set migration = '2026_09_19_000001_create_keel_tables'
where migration = '2026_09_19_000001_create_noria_tables';
```

The name `platform` in tenancy methods such as `asPlatform()` still refers to application-wide
operations across workspaces. It is independent of the Composer package name.

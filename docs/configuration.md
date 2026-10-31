# Configuration

[Documentation index](../README.md#documentation)

Publish the configuration with:

```bash
php artisan vendor:publish --tag=keel-config
```

Edit `config/keel.php` in your application. The [shipped configuration](../config/keel.php)
lists the available settings and environment variable names. Configure array settings in that
file; they do not all have environment variable equivalents.

## Database and migrations

| Setting | Purpose | Default |
|---|---|---|
| `keel.connection` | Named Laravel connection for package models and tenancy | Application default connection |
| `keel.table_prefix` | Prefix for package table names | Empty string |
| `keel.tables.<name>` | Explicit name for an individual package table | Standard package name |
| `keel.key_type` | Primary key of package tables: `uuid`, `ulid`, or `int` | `uuid` |
| `keel.tenancy.key_type` | Type of your workspace keys: `uuid`, `ulid`, `int`, or `string` | `uuid` |
| `keel.tenancy.column` | Name of the workspace column | `workspace_id` |
| `keel.load_migrations` | Automatically load the package migration | `true` |
| `keel.migrate.<name>` | Create an individual package table | `true` |
| `keel.timestamps` | Timezone-aware (`tz`) or plain timestamps | `tz` |

The package migration creates `audit_logs`, `otp_challenges`, and `invitations`. Turn one off
with `KEEL_MIGRATE_AUDIT_LOGS`, `KEEL_MIGRATE_OTP_CHALLENGES`, or `KEEL_MIGRATE_INVITATIONS` set
to `false`; rolling back then leaves a table of that name alone.

`keel.key_type` chooses how package rows are keyed. `uuid` generates version 7 UUIDs and `ulid`
generates lowercase ULIDs, both of which sort by creation time; `int` uses an auto-incrementing
big integer. The bundled `PersonalAccessToken` follows the same setting, so its table's `id`
column must match.

`keel.tenancy.key_type` must match the primary key of your workspace model. It types the
workspace column in the package migration, the cast in row-level security policies, and the
workspace identifiers token abilities accept. `string` accepts up to 64 letters, digits, dots,
underscores, and hyphens, for workspaces keyed on a slug.

Actor, target, inviter, and acceptor identifiers are stored as strings with no foreign keys, so
your user and other models can use any key type.

Set key types, table names, the workspace column, and the connection before the first migration.
Changing configuration after tables exist does not rename or alter them; add an application
migration for schema changes.

For full control over the migration, publish it:

```bash
php artisan vendor:publish --tag=keel-migrations
```

Then set `KEEL_LOAD_MIGRATIONS=false` so Laravel uses your published copy instead of also loading
the package copy. Disabling tenancy or audit recording does not stop the migration from creating
tables. Disable migration loading separately when those tables are unnecessary.

On PostgreSQL, the default timestamp mode uses `timestamptz`. Set the connection's `timezone` in
`config/database.php` to the same value as the application's timezone. For an application on UTC,
the connection entry should include:

```php
'timezone' => 'UTC',
```

The package migration checks the database session's timezone offset against the application's
offset. `keel:tenancy-check` also checks timestamp alignment. Choose `KEEL_TIMESTAMPS=plain`
if your application's schema requires timestamps without timezone awareness.

## Component setup

| Component | Setting or binding |
|---|---|
| Workspace context | `keel.tenancy.enabled`, `WorkspaceResolver`, `SetWorkspaceContext` middleware |
| Tenant schema | `keel.tenancy.column`, `key_type`, `workspace_guc`, `gucs`, `unscoped_tables` |
| Tenant jobs | `keel.tenancy.queue`, `overlap_expires_after` |
| Permission catalogue | `keel.rbac.resources`, `keel.rbac.actions` |
| Permission gate name | `keel.rbac.gate`, read back with `Keel::gate()` |
| Permission resolution | `PrincipalResolver`, `RoleRepository`, optional `PermissionCeiling` |
| Audit recording | `keel.audit.enabled`, `keel.audit.guard` |
| Cache store for scheduler heartbeats and social state | `keel.cache.store` |
| Shared log context | Optional `LogContext` binding |
| Invitation delivery | Optional `Courier` binding |
| OTP settings | `keel.auth.otp.length`, `ttl`, `attempts`, `throttle`, `hint` |
| Social state lifetime | `keel.auth.social.state_ttl` |
| Invitation settings | `keel.invitations.ttl_days`, `hint`, `token_guc` |

Keel resolves these contracts through Laravel's container. Your application implements the
contracts and registers bindings in a service provider's `register()` method. See the
[component guide](components.md) for the expected interfaces.

Keel sets and clears the settings named by `workspace_guc`, `staff_read_guc`,
`platform_write_guc`, and `keel.invitations.token_guc`, under whatever names you give them. List
any other setting your own code passes to `Tenancy::withGuc()` in `keel.tenancy.gucs`; Keel
refuses to set a setting it does not know to clear. Existing database policies refer to the
names used when they were created, so renaming one needs an application migration too.

The audit trail records the user from `keel.audit.guard`, or the default guard when it is unset.
`actor_type` is the user model's morph class, so a morph map alias such as `member` is stored
instead of the class name. A user that is not an Eloquent model is recorded as `user`, and no user
at all as `system`.

## Models

Extend the package model you want to customize, then register the subclass in your application's
service provider. For example, given an application model extending `Noria\Keel\Audit\AuditLog`:

```php
use App\Models\AuditLog;
use Noria\Keel\Keel;

Keel::useAuditLogModel(AuditLog::class);
```

The corresponding methods are `useOtpChallengeModel()` and `useInvitationModel()`. Each model
must extend its package base class. `Keel::forgetModels()` resets all three registrations, which
is useful when isolating tests.

## Country, phone numbers, and currency

The defaults are Kenya (`KE`) and Kenyan shillings (`KES`). Set `KEEL_COUNTRY` and `KEEL_CURRENCY`
for your application. Add country-to-dialling-code entries under `keel.identity.dialling_codes`
when accepting local phone numbers from additional countries. Numbers supplied with a leading
`+` are checked by the phone component's length rules without requiring a country-map entry.

Phone normalization uses configurable dialling codes and length checks. It does not validate
every country's numbering plan or verify that an address belongs to a user.

Money always stores hundredths of a major unit. `keel.money.fraction_digits` controls document
display precision by currency, with `keel.money.digits` as the fallback; the result is limited
to zero through two decimal places. Some shipped currency entries display no decimals. Set
explicit entries to match your application's requirements.

`keel.money.locales` maps currencies to display locales. Without an entry, Keel derives a locale
from the first two characters of the currency code. `Money::format()` also accepts an explicit
locale and precision. Currency formatting does not automatically follow `app.locale`.

## Hashes and address hints

Set `KEEL_HASH_KEY` for keyed hashes. If it is absent, Keel uses Laravel's `app.key`; hashing
throws if neither key is available. Keep the key stable: changing it changes destination and
token hashes and prevents existing values from being looked up using their original inputs.

`keel.invitations.hint` and `keel.auth.otp.hint` default to `masked`. Setting either to `plain`
stores the normalized destination beside its hash in readable form. Choose the setting based
on who can read those records.

## HTTP, logging, and utilities

| Setting | Purpose |
|---|---|
| `keel.http.security_headers.*` | Header directives, report-only mode, API paths, HSTS, development origin |
| `keel.http.trusted_proxies` | Proxies whose forwarded headers the middleware accepts |
| `keel.log.*` | Redaction keys, suffixes, mask style, exception channel, verbatim keys |
| `keel.csv.max_bytes` | Maximum content size for CSV upload rules and base64 decoding |
| `keel.errors.*` | Error codes, view prefix, stylesheet used by static page generation |
| `keel.scheduler.heartbeat_ttl` | Scheduler heartbeat expiry, in seconds |
| `keel.db.*` | Backup storage, encryption, retention, maintenance connections, rebuild checks |

See [operations](operations.md) for backup and deployment configuration.

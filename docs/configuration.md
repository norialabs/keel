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
| `keel.load_migrations` | Automatically load the package migration | `true` |
| `keel.timestamps` | Timezone-aware (`tz`) or plain timestamps | `tz` |

The package migration creates `audit_logs`, `otp_challenges`, and `invitations`. Package model
primary keys and the default workspace columns are UUIDs. Actor identifiers are strings so
applications can use integer or UUID user keys.

Set table names, the workspace column, and the connection before the first migration. Changing
configuration after tables exist does not rename or alter them; add an application migration for
schema changes.

The migration and `BelongsToWorkspace` trait use `keel.tenancy.column`. The bundled token
model's assignment and invitation acceptance currently access `workspace_id` directly. Using a
different tenant column name with those integrations requires adapted application implementations.

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
| Tenant schema | `keel.tenancy.column`, `workspace_guc`, `gucs`, `unscoped_tables` |
| Tenant jobs | `keel.tenancy.queue`, `overlap_expires_after` |
| Permission catalogue | `keel.rbac.resources`, `keel.rbac.actions` |
| Permission resolution | `PrincipalResolver`, `RoleRepository`, optional `PermissionCeiling` |
| Audit recording | `keel.audit.enabled` |
| Shared log context | Optional `LogContext` binding |
| Invitation delivery | Optional `Courier` binding |
| OTP settings | `keel.auth.otp.length`, `ttl`, `attempts`, `throttle`, `hint` |
| Social state lifetime | `keel.auth.social.state_ttl` |
| Invitation settings | `keel.invitations.ttl_days`, `hint`, `token_guc` |

Keel resolves these contracts through Laravel's container. Your application implements the
contracts and registers bindings in a service provider's `register()` method. See the
[component guide](components.md) for the expected interfaces.

When changing a PostgreSQL setting name such as `workspace_guc`, update `keel.tenancy.gucs` as
well. That list declares the settings Keel is allowed to set and must clear. Existing database
policies refer to the names used when they were created and need an application migration too.

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

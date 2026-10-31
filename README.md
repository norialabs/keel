# Keel

[![CI](https://github.com/norialabs/keel/actions/workflows/ci.yml/badge.svg)](https://github.com/norialabs/keel/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/norialabs/keel)](https://packagist.org/packages/norialabs/keel)

Modular application infrastructure for Laravel. Keel provides workspace tenancy, permissions,
audit logging, authentication utilities, invitations, and database operations, alongside HTTP,
money, and CSV components.

Use Keel in any Laravel application. You supply your application's models, authorization rules,
and delivery providers through contracts and configuration. No external account or service is
required.

## Requirements

- PHP 8.3 or newer and Laravel 13. CI covers PHP 8.3, 8.4, and 8.5.
- PostgreSQL for row-level security and database rebuilds. Other components can be used without it.
- Laravel Sanctum when using the optional workspace-scoped token model.
- PHP's `intl` extension for currency formatting, and `sodium` for encrypted backups.
- Database client tools when using database operations; see [operations](docs/operations.md).

## Components

| Component | Capabilities | Integration required |
|---|---|---|
| Tenancy | Workspace context, PostgreSQL policies, tenant-aware jobs | Workspace resolver, middleware, application table policies |
| Permissions | Resource/action enums, role grants, permission ceilings | Application enums, principal resolver, role repository |
| Audit | Actor, workspace, target, request context, append-only model | Call the recorder; add database protections where required |
| Identity and authentication | Address normalization, keyed hashes, OTP verification, social state | User lookup, message delivery, routes, login flow |
| Tokens | Workspace-scoped Sanctum model and permission abilities | Sanctum, compatible token table, model registration |
| Invitations | Expiring tokens, recipient checks, revocation | Membership creation; optional delivery contract |
| Database operations | Backups, retention, encryption, restore, PostgreSQL rebuild | Storage disk, database tools and privileges |
| HTTP and errors | Security headers, trusted proxies, static error pages | Middleware registration, application views/assets |
| Money | Integer amounts, currency checks, formatting, validation | Currency and display settings |
| CSV | Preview, streamed rows/exports, upload validation | Application import/export flow |

Read the [component guide](docs/components.md) for examples and integration contracts.

## Install

```bash
composer require norialabs/keel
php artisan vendor:publish --tag=keel-config
```

Laravel discovers `Noria\Keel\KeelServiceProvider` automatically. Settings are published to
`config/keel.php`. All package classes use the `Noria\Keel\` namespace.

Before migrating, choose which components your application needs. The provider registers services
and commands, and loads the package migration by default. That migration creates `audit_logs`,
`otp_challenges`, and `invitations`; it does not create users, workspaces, roles, memberships,
or a Sanctum token table.

Tenancy and audit recording are enabled in configuration by default. HTTP middleware, message
delivery, and application-specific permission bindings require explicit setup. PostgreSQL policies
and database triggers must be added in your application's migrations; installing Keel alone does
not establish tenant isolation.

### Applications without PostgreSQL tenancy

Set this in your application's `.env` before using services that manage workspace context:

```dotenv
KEEL_TENANCY=false
```

If you only need components that do not use package tables, such as CSV, money, or HTTP middleware,
also disable automatic migration loading and audit recording:

```dotenv
KEEL_LOAD_MIGRATIONS=false
KEEL_AUDIT=false
```

### Applications using package tables

Choose key types, table names, the workspace column, and your database connection before running.
Package tables can be keyed on UUIDs, ULIDs, or integers, and your workspaces on UUIDs, ULIDs,
integers, or slugs:

```bash
php artisan migrate
```

For PostgreSQL, align the connection's `timezone` with `config('app.timezone')` before migrating.
See [database configuration](docs/configuration.md#database-and-migrations).

For workspace isolation, continue with [tenancy setup](docs/components.md#workspace-tenancy).

### Quick example

The money component can be used without package tables or application model bindings:

```php
use Noria\Keel\Money\Money;

$unitPrice = Money::fromMajor('12.50', 'USD');
$total = $unitPrice->times(3);
$amount = $total->rate();
```

`$amount` is `"37.50"`. Money uses a fixed storage scale of 100; review the
[money component](docs/components.md#money) before choosing it for your currency requirements.

## Configuration

Settings live under `keel.*`, environment variables under `KEEL_*`, and Artisan commands under
`keel:*`. Key types, table and column names, the connection, which tables are created, models,
the audit guard, the cache store, and the permission gate name are all configurable, and the
package dispatches events for audit entries, sign-in codes, and invitations.

The shipped country and currency defaults are `KE` and `KES`. Configure them for your application;
the dialling-code map and currency display rules are explicit settings rather than a complete
internationalization database. See [configuration](docs/configuration.md).

## Documentation

- [Components and integration contracts](docs/components.md)
- [Configuration, migrations, and customization](docs/configuration.md)
- [Database operations and deployment tasks](docs/operations.md)
- [Upgrading from Platform](docs/upgrading.md)
- [Contributing and running tests](CONTRIBUTING.md)

## Support and contributions

Report reproducible bugs or propose improvements through
[GitHub issues](https://github.com/norialabs/keel/issues). Include your PHP, Laravel, and
database versions, the component involved, and a minimal example. Pull requests are welcome;
see [the contribution guide](CONTRIBUTING.md) for local setup and checks.

## License

Keel is available under the [MIT license](LICENSE).

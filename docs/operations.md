# Operations

[Documentation index](../README.md#documentation)

Keel's commands run inside your Laravel application and use its database connections, filesystem
disks, views, and scheduler. They do not require a particular hosting or cloud provider.

## Database tools and capabilities

| Driver | Backup tooling | Restore and maintenance |
|---|---|---|
| PostgreSQL | `pg_dump` | `psql`; supports replacement, alternate databases, and rebuilds |
| MySQL / MariaDB | `mysqldump`, plus `gzip` when compression is enabled | `mysql`, plus `gunzip` for compressed dumps |
| SQLite | File copy | File-based databases only; no in-memory backups |

PostgreSQL maintenance operations require suitable database privileges. Keep the client tools
compatible with your server; CI exercises PostgreSQL 16. The SQLite test run does not establish
PostgreSQL isolation or MySQL integration coverage.

Register additional drivers through `DumperFactory::register($driver, $dumperClass)`. Custom
dumpers implement `DatabaseDumper`; alternate-database maintenance and transactional replacement
are separate capabilities defined by `DatabaseMaintainer` and `DatabaseReplacer`.

## Backups and retention

Configure `keel.db.disk` with a Laravel filesystem disk and choose a writable working directory
through `keel.db.working_directory` when the default location is unsuitable.

```bash
php artisan keel:backup
php artisan keel:backup --disk=backups --tier=daily --connection=pgsql
```

The second example assumes your application defines a `backups` disk and `pgsql` connection.

For PostgreSQL, configure `keel.db.admin_connection` with a maintenance connection whose role
can read all rows despite row-level security. The PostgreSQL dumper checks this before dumping.
The constrained application role should remain separate from this maintenance role.

`keel.db.tiers.hourly` and `daily` configure storage prefixes and retention. Unless a tier is
explicitly selected, the backup service chooses between hourly storage and a daily promotion
using the configured daily hour. It retries storage operations according to `keel.db.attempts`.
A failure to prune old backups is logged without discarding the completed upload.

Backups run when you invoke the command. Keel does not automatically schedule them. For example,
in your application's `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('keel:backup')->hourly();
```

### Encryption

Set `KEEL_BACKUP_ENCRYPTION_KEY` to `base64:` followed by a base64-encoded 32-byte key. Generate
the encoded bytes with:

```bash
openssl rand -base64 32
```

Encrypted dumps use libsodium's XChaCha20-Poly1305 streaming format and end in `.enc`. Restore
requires the same key and rejects modified or truncated encrypted streams. Dumps remain
unencrypted when no backup encryption key is configured. Older unencrypted dumps can still be
restored. Keep the decryption key available independently of the backup storage.

## Restore

```bash
php artisan keel:restore --disk=backups --database=restore_check
```

This selects the newest backup across both tiers and, for PostgreSQL, restores into the named
database. The maintenance role needs permission to create that database if it does not exist.
Without `--database`, restore targets the configured connection's database. You can supply an
explicit backup key as the positional argument.

The command prompts before restoring. `--force` skips the prompt and permits execution when
Laravel reports a production environment. Driver capabilities differ: PostgreSQL's table
replacement and dump load run in one transaction, while other dumpers do not provide that same
guarantee.

For PostgreSQL, restore detects extensions named in the dump and installs missing ones before
loading it. If installation requires elevated privileges, configure
`keel.db.superuser_connection`, or install the reported extensions yourself. Extension binaries
must already be available on the database server. The restore path removes extension ownership
comments that the restoring role may not be allowed to reapply.

## PostgreSQL schema rebuild

`keel:rebuild` recreates the schema from current migrations and reloads existing rows. It is
intended for cases where the database schema has drifted from those migrations. It requires
PostgreSQL, compatible client tools, and maintenance privileges.

```bash
php artisan keel:rebuild --keep
```

The command checks a probe schema before proceeding. Tables and columns the migrations no longer
define are dropped; when they still hold rows, the untouched copy is first archived as a
`-retired.dump` in the working directory and the report names each one with its row count. A
type change, a narrowing, or a new `not null` column without a default still refuses to proceed.
The command then creates a database copy, dumps its data, rebuilds the schema, reloads rows, and
checks the result against the copy. `--keep` retains the copy and dump after success. On failure after the copy is made, the error identifies the copy and a
recovery command.

`keel.db.rebuild.unrestored` lists tables whose contents are excluded; its default includes
`migrations`. Configure `keel.db.rebuild.verify_commands` for application checks after reload.
Use repeated `--bring-up` options for commands that adapt reloaded rows before final validation.
Constraints originally added as `NOT VALID` are restored with that status.

The command requires exclusive database access and refuses production execution without
`--force`. Plan a maintenance window appropriate to the size of your application database.

## Scheduler health

Schedule a heartbeat in your application:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('keel:scheduler-heartbeat')->everyMinute();
```

Run `php artisan keel:scheduler-healthy` from your deployment's health check. The command uses
`keel.scheduler.heartbeat_ttl` to determine whether a recent heartbeat exists. The health check
and scheduler need access to the same persistent Laravel cache store.

## Static error pages

Provide views such as `resources/views/errors/502.blade.php` and
`resources/views/errors/504.blade.php`, or configure another prefix with `keel.errors.view`.
Views can render the shared `$errorInlineCss` value to include the configured stylesheet.

Build the stylesheet configured by `keel.errors.stylesheet` through your application's asset
pipeline, then run:

```bash
php artisan keel:build-error-pages
```

The command writes `public/502.html`, `public/504.html`, or the codes selected by
`keel.errors.codes`. Configure your reverse proxy or web server to serve those files when the
application is unavailable. Keel does not configure the server itself.

Set the stylesheet setting to an empty string or null if your views do not need generated CSS.

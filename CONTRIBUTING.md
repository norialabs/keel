# Contributing to Keel

Bug reports, documentation improvements, and pull requests are welcome. Describe the behavior
you want to change and include a reproducible example where possible. Examples should work
without private applications, accounts, or datasets.

## Local setup

Clone the repository and run these commands from its root:

```bash
composer install
composer validate --strict
```

The package targets Laravel 13 and PHP 8.3 or newer. It uses Orchestra Testbench to run Laravel
inside its tests; you do not need to create a separate Laravel application for the suite.

## Checks

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
./vendor/bin/pest
```

Use `./vendor/bin/pint` to apply formatting. PHPStan runs at its maximum level. Keep code
self-explanatory and retain PHPDoc annotations needed for types; put explanatory documentation
in the Markdown guides.

The default test run uses SQLite. PostgreSQL-only tests skip in that mode, so a successful SQLite
run does not verify tenant isolation, PostgreSQL backups, or database rebuilds.

## PostgreSQL integration tests

Use a disposable local PostgreSQL database: the integration suite creates, drops, restores, and
rebuilds test databases. Install `psql` and `pg_dump` compatible with the server. CI uses
PostgreSQL 16.

Connect to PostgreSQL with a local administrative role and create the test roles and database:

```sql
create role keel_test login password 'keel_test' nosuperuser nobypassrls;
create database keel_test owner keel_test;
create role keel_admin login password 'keel_admin' nosuperuser bypassrls createdb in role keel_test;
```

Then, while connected to `keel_test` as that administrator:

```sql
grant all on schema public to keel_test;
```

Run the full suite with all three connections supplied to the same test database:

```bash
KEEL_TEST_PG='pgsql://keel_test:keel_test@127.0.0.1:5432/keel_test' \
KEEL_TEST_PG_ADMIN='pgsql://keel_admin:keel_admin@127.0.0.1:5432/keel_test' \
KEEL_TEST_PG_SUPERUSER='pgsql://postgres:postgres@127.0.0.1:5432/keel_test' \
./vendor/bin/pest
```

Replace the superuser credentials with those for your disposable local server. The constrained
role verifies row-level security; the maintenance role exercises backups and restores; the
superuser connection covers extension installation. Missing connections or a role that bypasses
row-level security cause the relevant tests to skip. Check the skipped count as well as the exit
status.

## Documentation and pull requests

Keep examples consistent with the package's namespaces, defaults, and integration contracts.
Document application responsibilities explicitly, particularly for authorization, message
delivery, schema policies, and operations that need database privileges.

Submit changes on a branch and open a pull request against `main`. Include a short explanation
of the resulting behavior and the checks you ran. Identify skipped database checks and any
compatibility changes. Releases are handled by the repository's automation after merging.

You can check the distributable package locally with:

```bash
composer archive --format=zip --dir=/tmp
```

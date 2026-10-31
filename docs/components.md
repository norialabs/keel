# Components

[Documentation index](../README.md#documentation)

Keel provides services and contracts that integrate with your application's domain. Register
application bindings in a service provider's `register()` method. Classes under `App\` in these
examples refer to code you implement in your application.

## Workspace tenancy

A workspace is the tenant context for a request or job. Your application owns workspaces,
membership checks, and the process for selecting a workspace.

Implement [WorkspaceResolver](../src/Contracts/WorkspaceResolver.php), whose
`resolve(Request $request): ?string` method returns an authorized workspace identifier. Register
the implementation with Laravel's container:

```php
use App\Tenancy\RequestWorkspaceResolver;
use Noria\Keel\Contracts\WorkspaceResolver;

$this->app->bind(WorkspaceResolver::class, RequestWorkspaceResolver::class);
```

Add `Noria\Keel\Http\Middleware\SetWorkspaceContext` to the routes that need tenant context,
after any authentication middleware your resolver depends on. Allow Laravel to call its
`terminate()` method, which clears connection settings after the request.

For PostgreSQL isolation, use an application role with `NOSUPERUSER NOBYPASSRLS`. Add a workspace
column to your tenant tables and install policies in application migrations after creating the
tables:

```php
use Noria\Keel\Tenancy\Rls;

Rls::protect('projects', staffRead: true);
```

`protect()` enables and forces row-level security, including for the table owner. Superusers
and roles with `BYPASSRLS` are exempt from those policies.

Models can use `Noria\Keel\Concerns\BelongsToWorkspace` to stamp the configured workspace column
on creation. The trait also provides `forWorkspace()` for explicit filtering. It does not add
an automatic global scope or install database policies.

Run application work in an explicit context:

```php
use Noria\Keel\Tenancy\Tenancy;

$result = app(Tenancy::class)->run($workspaceId, fn () => $service->execute());
```

`run()` restores the previous context when the callback finishes. `TenantJob` provides a base
for jobs that need a workspace context and a workspace-specific overlap lock; its queue and lock
expiry are configurable.

| PostgreSQL helper | Behavior |
|---|---|
| `Rls::protectAllowingGlobal()` | Read matching workspace rows and null-workspace rows; writes require the active workspace |
| `Rls::allowStaffRead()` | Allow cross-workspace reads while the staff setting is active |
| `Rls::allowPlatformWrite()` | Allow access while the application-wide write setting is active |
| `Rls::protectTrail()` | Workspace reads, nullable-workspace inserts, and staff reads |
| `Rls::allowLookupByGuc()` | Allow lookup through a PostgreSQL session setting |
| `Rls::defineRejectMutation()` and `appendOnly()` | Define a mutation-rejection function and attach a table trigger |

`Tenancy::asStaff()` and `asPlatform()` temporarily activate the corresponding settings. Here,
"platform" means your application's cross-workspace operations. These methods do not authorize
the caller; your application decides when to allow that access.

Every setting used by `withGuc()` must appear in `keel.tenancy.gucs`. `clear()` resets that list,
including settings that widen access. Run `php artisan keel:tenancy-check` to inspect the role,
unprotected tenant tables, unforced policies, and timestamp alignment. Use
`keel.tenancy.unscoped_tables` for tables deliberately outside tenancy.

## Permissions

Define your application's resource and action catalogues as backed enums implementing
[PermissionResource](../src/Contracts/PermissionResource.php) and
[PermissionAction](../src/Contracts/PermissionAction.php). For example:

```php
namespace App\Enums;

use Noria\Keel\Contracts\PermissionAction;
use Noria\Keel\Contracts\PermissionResource;

enum Action: string implements PermissionAction
{
    case View = 'view';
    case Update = 'update';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

enum Resource: string implements PermissionResource
{
    case Project = 'project';

    public function label(): string
    {
        return 'Project';
    }

    public function actions(): array
    {
        return [Action::View, Action::Update];
    }

    public function supports(PermissionAction $action): bool
    {
        return in_array($action, $this->actions(), true);
    }
}
```

Set `keel.rbac.resources` to `App\Enums\Resource::class` and `keel.rbac.actions` to
`App\Enums\Action::class`. Implement and bind these contracts:

| Contract | Application responsibility |
|---|---|
| [PrincipalResolver](../src/Contracts/PrincipalResolver.php) | Resolve an authenticated user to a `Principal`, or return null |
| [Principal](../src/Contracts/Principal.php) | Supply scope, role slugs, and a context identifier |
| [RoleRepository](../src/Contracts/RoleRepository.php) | Return `Permissions` for the given scope and role slugs |
| [PermissionCeiling](../src/Contracts/PermissionCeiling.php) | Optionally restrict the user's merged permissions |

The provider registers the permission gate only when `PrincipalResolver` and `RoleRepository`
are bound. Once configured:

```php
use App\Enums\Action;
use App\Enums\Resource;
use Illuminate\Support\Facades\Gate;
use Noria\Keel\KeelServiceProvider;

Gate::authorize(KeelServiceProvider::GATE, [Resource::Project, Action::Update]);
```

`Permissions::fromArray()` validates grant data against the configured catalogue. Role grants
are merged before applying a ceiling. Implement `ScopedPermissionResource` when your application
has separate catalogues for different user groups; `Catalog::forScope()` and
`Permissions::catalog($scope)` accept strings or backed enums.

## Audit logging

Call the recorder for application events:

```php
use Noria\Keel\Audit\AuditRecorder;

app(AuditRecorder::class)->record(
    'project.updated',
    targetType: 'project',
    targetId: (string) $project->getKey(),
);
```

The recorder captures the authenticated actor, current workspace, target, metadata, reason, and
request context. Unauthenticated work is recorded with actor type `system`. Setting
`KEEL_AUDIT=false` makes `record()` return null without writing a row.

`AuditLog` rejects Eloquent instance updates and deletes through `AppendOnly`. Raw SQL and
query-builder operations do not run those model events. For PostgreSQL database enforcement,
add these calls in a migration after the audit table exists:

```php
use Noria\Keel\Keel;
use Noria\Keel\Tenancy\Rls;

Rls::defineRejectMutation();
Rls::appendOnly(Keel::table('audit_logs'));
```

The package migration does not install audit read policies. Decide which users can read the
trail and apply authorization and database policies appropriate to your application.

## Identity and authentication

`Destination::tryFrom()` normalizes an email address or phone number and returns null for invalid
input. `Phone` uses configured dialling codes and basic length checks. Normalization does not
prove ownership; your application handles verification.

`KeyedHash` provides HMAC-SHA-256 lookup hashes. Configure a stable hash key as described in
[configuration](configuration.md#hashes-and-address-hints).

Issue an email OTP:

```php
use Noria\Keel\Auth\Otp;
use Noria\Keel\Identity\Channel;
use Noria\Keel\Identity\Destination;

$destination = Destination::tryFrom('person@example.com');

if ($destination !== null) {
    $code = app(Otp::class)->issue($destination, Channel::Email);
}
```

Deliver the returned code through your application. Pass the destination and submitted code to
`Otp::verify()` and handle the returned `OtpOutcome`: `Verified`, `Incorrect`, `Expired`,
`Exhausted`, or `NoChallenge`. A successful verification consumes the challenge; your application
then performs user lookup and login. `issue()` throws `OtpThrottled` when the destination must
wait before requesting another code.

The service hashes codes and destinations, tracks verification attempts, and replaces pending
challenges when issuing a new code. It does not send messages or register routes, sessions, or
user models. Schedule `keel:prune-otp` to remove old expired challenges.

`SocialState::issue($provider, $actorId)` creates state for an authentication round trip.
`claim($state)` retrieves and removes its cached provider/actor data. Your callback must validate
the returned provider and bind the flow to the initiating browser. `ProviderProfile` normalizes
provider identity fields without retaining provider tokens.

## Sanctum tokens

Install Sanctum when using `Noria\Keel\Auth\PersonalAccessToken`:

```bash
composer require laravel/sanctum
```

Register the model in your application's service provider:

```php
use Laravel\Sanctum\Sanctum;
use Noria\Keel\Auth\PersonalAccessToken;

Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
```

Your token table must support UUID primary keys and a UUID `workspace_id` column. Keel
does not migrate that table. The model requires exactly one `workspace:{uuid}` ability and
derives the workspace column from it; missing, duplicate, or invalid workspace abilities cause
`UnscopedToken` to be thrown. The bundled token model assigns `workspace_id` directly; adapt
the model if your application uses another tenant column name.

`TokenAbilities::forWorkspace()` and `permission()` construct ability strings.
`TokenCeiling` implements `PermissionCeiling` to restrict role grants to the current token's
permissions. Bind it explicitly if you want that integration.

## Invitations

`Invitations::invite()` accepts a destination, role, delivery channel, and optional workspace ID.
If the workspace is omitted, it uses the current context. The result contains the invitation
model and plaintext token; the stored token is hashed. Reissuing replaces open invitations for
the same destination in that workspace.

Bind [Courier](../src/Contracts/Courier.php) to deliver invitations through your mail, SMS, or
messaging provider. Without a courier, `invite()` still returns the token for your application
to deliver. Check that the destination suits the chosen channel.

`find()` checks the token, expiry, and open status. `accept()` also compares the supplied
destination with the invitation's destination hash and marks the invitation accepted. Supply a
destination your application has verified belongs to the accepting user. Your application
creates memberships and interprets role names; Keel does not do that automatically.

For PostgreSQL, add tenant protection and token lookup after the table exists:

```php
use Illuminate\Support\Facades\Config;
use Noria\Keel\Keel;
use Noria\Keel\Tenancy\Rls;

Rls::protect(Keel::table('invitations'));
Rls::allowLookupByGuc(
    Keel::table('invitations'),
    'token_hash',
    Config::string('keel.invitations.token_guc'),
);
```

The token setting must also be declared in `keel.tenancy.gucs`. `revoke()` withdraws an open
invitation, and `prune()` deletes sufficiently old expired invitations.

## Logging

`Logger::app()`, `auth()`, `backup()`, and `exception()` redact structured context and forward it
to Laravel's logging system. Named channels fall back to the default channel when undefined.
Any Laravel logging driver can handle delivery.

Add application-specific redaction keys and suffixes through `keel.log.*`. A `LogContext`
binding can supply ambient context. Redaction applies to context values, so keep sensitive data
out of the message string and review `verbatim_keys` for your application's payloads.

## HTTP security and static errors

Register `Noria\Keel\Http\Middleware\SecurityHeaders` for routes that need the configured CSP,
permissions policy, HSTS, and other headers. Customize directives for your application's assets,
embeds, API paths, and development server.

Use `Noria\Keel\Http\Middleware\TrustProxies` to load trusted proxies from
`keel.http.trusted_proxies`. An unset proxy configuration trusts no proxies. Configure the
actual proxy addresses or ranges used by your deployment.

Static error page generation requires application views and built assets; see
[deployment tasks](operations.md#static-error-pages).

## Money

`Money` stores an integer amount and currency. Currency checks prevent adding or subtracting
amounts of different currencies. `fromMajor()` parses a decimal string, rounds to hundredths,
and rejects invalid input or amounts beyond `keel.money.max_minor`.

```php
use Noria\Keel\Money\Money;

$price = Money::fromMajor('2.75', 'USD');
$lineTotal = $price->times(4);
```

**The storage scale is always 100, for every currency.** Display precision is configurable from
zero to two decimal places. This is not an ISO currency minor-unit table and does not represent
currencies requiring three fractional digits at their native scale.

| Method | Result |
|---|---|
| `format()` | Localized amount with currency symbol |
| `formatUnitPrice()` | Localized amount with two decimals |
| `document()` | Decimal string at the configured currency display precision |
| `rate()` | Decimal string with two decimals |
| `rules()` | Validation rules for integer amounts |
| `majorRules()` | Validation rules for decimal-string amounts |

`times()` accepts integer or fractional quantities. Multiplication uses a floating-point
intermediate, rounds to an integer once, and checks the configured bound. Configure currency
locales explicitly when a derived locale does not match your intended display. `MoneyCast`
integrates the value object with Eloquent.

## CSV

`Reader` handles delimiter detection, UTF-8 BOMs, normalized headers, and row iteration.
`Writer` creates UTF-8 CSV exports and prefixes data cells that begin with spreadsheet formula
characters.

```php
use Noria\Keel\Csv\Reader;
use Noria\Keel\Csv\Writer;

$csv = "name,email\nAlex,alex@example.com\n";
$preview = Reader::previewContent($csv);
$export = Writer::toString(['name', 'email'], [['Alex', 'alex@example.com']]);
```

Use `preview()` and `rows()` for paths, or `previewContent()`, `rowsContent()`, and
`countContent()` for strings. `decode()` bounds base64 input before decoding. `uploadRules()`
validates raw or base64 CSV content supplied as strings; it is not an uploaded-file validator.
Use `Writer::download()` for a streamed HTTP response.

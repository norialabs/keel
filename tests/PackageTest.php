<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Noria\Keel\Audit\AuditLog;
use Noria\Keel\Audit\AuditRecorder;
use Noria\Keel\Auth\Otp;
use Noria\Keel\Auth\OtpChallenge;
use Noria\Keel\Auth\OtpOutcome;
use Noria\Keel\Http\Middleware\SecurityHeaders;
use Noria\Keel\Identity\Destination;
use Noria\Keel\Invitations\Invitations;
use Noria\Keel\Keel;
use Noria\Keel\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

describe('naming the tables', function (): void {
    it('creates the tables under the names the models will look for', function (): void {
        foreach (['audit_logs', 'otp_challenges'] as $name) {
            expect(Schema::hasTable(Keel::table($name)))->toBeTrue("missing {$name}");
        }
    });

    it('prefixes every table so a host can keep them out of its own namespace', function (): void {
        config(['keel.table_prefix' => 'keel_']);

        expect((new AuditLog)->getTable())->toBe('keel_audit_logs');
        expect((new OtpChallenge)->getTable())->toBe('keel_otp_challenges');
    });

    it('renames one table without spelling out the others', function (): void {
        config(['keel.tables.audit_logs' => 'activity']);

        expect((new AuditLog)->getTable())->toBe('activity');
        expect((new OtpChallenge)->getTable())->toBe('otp_challenges');
    });

    it('creates only the tables the product wants', function (): void {
        remigrate(['keel.migrate.invitations' => false]);

        expect(Schema::hasTable('invitations'))->toBeFalse();
        expect(Schema::hasTable('audit_logs'))->toBeTrue();
        expect(Schema::hasTable('otp_challenges'))->toBeTrue();
    });

    it('leaves a table it did not create alone when rolled back', function (): void {
        remigrate(['keel.migrate.invitations' => false]);
        Schema::create('invitations', fn ($table) => $table->id());

        (require __DIR__.'/../database/migrations/2026_09_19_000001_create_keel_tables.php')->down();

        expect(Schema::hasTable('invitations'))->toBeTrue();
        expect(Schema::hasTable('audit_logs'))->toBeFalse();
    });
});

describe('choosing the keys', function (): void {
    it('keys every table on a uuid unless told otherwise', function (): void {
        foreach (['audit_logs', 'otp_challenges', 'invitations'] as $name) {
            expect(Schema::getColumnType(Keel::table($name), 'id'))->not->toBe('integer');
        }

        expect(Str::isUuid(app(AuditRecorder::class)->record('invoice.voided')?->id))->toBeTrue();
    });

    it('generates version 7 identifiers, so rows sort by when they were written', function (): void {
        expect(explode('-', (new AuditLog)->newUniqueId())[2][0])->toBe('7');
    });

    it('keys every table on a ulid when the product prefers them', function (): void {
        remigrate(['keel.key_type' => 'ulid']);

        $entry = app(AuditRecorder::class)->record('invoice.voided');
        ['invitation' => $invitation] = app(Invitations::class)->invite(Destination::tryFrom('ada@example.com') ?? throw new RuntimeException, 'member');

        expect(Str::isUlid((string) $entry?->id))->toBeTrue();
        expect(Str::isUlid((string) $invitation->id))->toBeTrue();
        expect(AuditLog::query()->find($entry?->id))->not->toBeNull();
    });

    it('keys every table on an auto-incrementing integer when the product prefers them', function (): void {
        remigrate(['keel.key_type' => 'int']);

        $first = app(AuditRecorder::class)->record('invoice.voided');
        $second = app(AuditRecorder::class)->record('invoice.paid');

        expect($first?->id)->toBeInt();
        expect($second?->id)->toBeGreaterThan($first?->id);
        $ada = Destination::tryFrom('ada@example.com') ?? throw new RuntimeException;
        $code = app(Otp::class)->issue($ada);

        expect(OtpChallenge::query()->sole()->id)->toBeInt();
        expect(app(Otp::class)->verify($ada, $code))->toBe(OtpOutcome::Verified);
    });

    it('refuses a string key it would have no way to generate', function (): void {
        config(['keel.key_type' => 'string']);

        app(AuditRecorder::class)->record('invoice.voided');
    })->throws(InvalidArgumentException::class, 'cannot generate a string key');

    it('refuses a key type it does not know', function (): void {
        config(['keel.key_type' => 'snowflake']);

        app(AuditRecorder::class)->record('invoice.voided');
    })->throws(InvalidArgumentException::class, 'not snowflake');

    it('types the workspace column to match the product workspaces', function (): void {
        remigrate(['keel.tenancy.key_type' => 'int']);

        app(Tenancy::class)->run('42', fn () => app(AuditRecorder::class)->record('invoice.voided'));

        expect(Schema::getColumnType('audit_logs', 'workspace_id'))->toBeIn(['integer', 'int8', 'bigint']);
        expect((string) AuditLog::query()->sole()->workspace_id)->toBe('42');
    });
});

describe('security headers', function (): void {
    function respond(?Response $response = null): Response
    {
        return (new SecurityHeaders)->handle(Request::create('/'), fn () => $response ?? new Response);
    }

    it('sends the headers every response should carry', function (): void {
        $headers = respond()->headers;

        expect($headers->get('X-Content-Type-Options'))->toBe('nosniff');
        expect($headers->get('X-Frame-Options'))->toBe('DENY');
        expect($headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
        expect($headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
    });

    it('lets a product relax one directive without dropping the middleware', function (): void {
        config(['keel.http.security_headers.directives.script-src' => ["'self'", 'https://cdn.example.com']]);

        expect(respond()->headers->get('Content-Security-Policy'))->toContain('https://cdn.example.com');
    });

    it('reports rather than enforces while a product is still finding its directives', function (): void {
        config(['keel.http.security_headers.report_only' => true]);

        expect(respond()->headers->get('Content-Security-Policy-Report-Only'))->not->toBeNull();
        expect(respond()->headers->get('Content-Security-Policy'))->toBeNull();
    });

    it('lets a pdf be framed by the page that opened it', function (): void {
        $pdf = new Response('', 200, ['Content-Type' => 'application/pdf']);

        expect(respond($pdf)->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
        expect(respond($pdf)->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    });

    it('sends nothing at all when a product turns it off', function (): void {
        config(['keel.http.security_headers.enabled' => false]);

        expect(respond()->headers->get('Content-Security-Policy'))->toBeNull();
    });

    it('holds transport security back until the request is actually secure', function (): void {
        expect(respond()->headers->get('Strict-Transport-Security'))->toBeNull();
    });

    it('gives an api route a policy naming no origin at all', function (): void {
        $response = (new SecurityHeaders)->handle(Request::create('/api/plans'), fn () => new Response);

        expect($response->headers->get('Content-Security-Policy'))
            ->toBe("default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    });

    it('leaves a page route on the wider policy', function (): void {
        expect(respond()->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
    });

    it('matches the api paths the product named, not a hardcoded one', function (): void {
        config(['keel.http.security_headers.api.paths' => ['webhooks/*']]);

        $api = (new SecurityHeaders)->handle(Request::create('/webhooks/mpesa'), fn () => new Response);
        $page = (new SecurityHeaders)->handle(Request::create('/api/plans'), fn () => new Response);

        expect($api->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
        expect($page->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
    });

    it('names no dev origin once the assets are built', function (): void {
        expect(respond()->headers->get('Content-Security-Policy'))->not->toContain('localhost:5173');
    });
});

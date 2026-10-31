<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Noria\Keel\Auth\Otp;
use Noria\Keel\Auth\OtpChallenge;
use Noria\Keel\Auth\OtpOutcome;
use Noria\Keel\Auth\OtpThrottled;
use Noria\Keel\Auth\PersonalAccessToken;
use Noria\Keel\Auth\ProviderProfile;
use Noria\Keel\Auth\SocialState;
use Noria\Keel\Auth\TokenAbilities;
use Noria\Keel\Auth\UnscopedToken;
use Noria\Keel\Events\OtpAttempted;
use Noria\Keel\Events\OtpIssued;
use Noria\Keel\Identity\Channel;
use Noria\Keel\Identity\Destination;
use Noria\Keel\Keel;
use Noria\Keel\Tests\Fixtures\HostOtpChallenge;

function to(string $raw): Destination
{
    return Destination::tryFrom($raw) ?? throw new InvalidArgumentException("[{$raw}] is not a destination.");
}

it('issues a code of the configured length', function (): void {
    config(['keel.auth.otp.length' => 8]);

    expect(app(Otp::class)->issue(to('ada@example.com')))->toHaveLength(8);
});

it('never issues a code a leading zero could be eaten from', function (): void {
    $otp = app(Otp::class);

    foreach (range(1, 40) as $n) {
        expect($otp->issue(to("person{$n}@example.com")))->not->toStartWith('0');
    }
});

it('stores neither the code nor the address in the clear', function (): void {
    $code = app(Otp::class)->issue(to('ada@example.com'));

    $challenge = OtpChallenge::query()->sole();

    expect($challenge->code_hash)->not->toBe($code);
    expect($challenge->destination_hash)->not->toContain('ada@example.com');
    expect($challenge->destination_hint)->toBe('a**@example.com');
});

it('accepts the code it issued', function (): void {
    $otp = app(Otp::class);
    $code = $otp->issue(to('ada@example.com'));

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::Verified);
});

it('treats the address as the same address whatever the casing', function (): void {
    $otp = app(Otp::class);
    $code = $otp->issue(to('Ada@Example.com '));

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::Verified);
});

it('refuses a code that was never issued', function (): void {
    expect(app(Otp::class)->verify(to('ada@example.com'), '123456'))->toBe(OtpOutcome::NoChallenge);
});

it('refuses the wrong code', function (): void {
    $otp = app(Otp::class);
    $otp->issue(to('ada@example.com'));

    expect($otp->verify(to('ada@example.com'), '000000'))->toBe(OtpOutcome::Incorrect);
});

it('refuses a code that has expired', function (): void {
    $otp = app(Otp::class);
    $code = $otp->issue(to('ada@example.com'));

    Carbon::setTestNow(Carbon::now()->addMinutes(11));

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::Expired);

    Carbon::setTestNow();
});

it('stops accepting guesses after the configured number of them', function (): void {
    config(['keel.auth.otp.attempts' => 3]);

    $otp = app(Otp::class);
    $code = $otp->issue(to('ada@example.com'));

    foreach (range(1, 3) as $ignored) {
        $otp->verify(to('ada@example.com'), '000000');
    }

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::Exhausted);
});

it('counts a guess before checking it, so a crash costs an attempt rather than granting one', function (): void {
    $otp = app(Otp::class);
    $otp->issue(to('ada@example.com'));
    $otp->verify(to('ada@example.com'), '000000');

    expect(OtpChallenge::query()->sole()->attempts)->toBe(1);
});

it('refuses the last guess a concurrent request already spent', function (): void {
    config(['keel.auth.otp.attempts' => 3]);

    $otp = app(Otp::class);
    $code = $otp->issue(to('ada@example.com'));

    OtpChallenge::retrieved(function (OtpChallenge $challenge): void {
        OtpChallenge::query()->whereKey($challenge->getKey())->update(['attempts' => 3]);
    });

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::Exhausted)
        ->and(OtpChallenge::query()->sole()->attempts)->toBe(3);
});

it('lets only one of two requests carrying the right code sign in', function (): void {
    $otp = app(Otp::class);
    $code = $otp->issue(to('ada@example.com'));

    OtpChallenge::retrieved(function (OtpChallenge $challenge): void {
        OtpChallenge::query()->whereKey($challenge->getKey())->update(['consumed_at' => now()]);
    });

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::NoChallenge);
});

it('cannot be used twice', function (): void {
    $otp = app(Otp::class);
    $code = $otp->issue(to('ada@example.com'));

    $otp->verify(to('ada@example.com'), $code);

    expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::NoChallenge);
});

it('cancels the outstanding code when a new one is asked for', function (): void {
    config(['keel.auth.otp.throttle' => 0]);

    $otp = app(Otp::class);
    $first = $otp->issue(to('ada@example.com'));
    $second = $otp->issue(to('ada@example.com'));

    expect($otp->verify(to('ada@example.com'), $first))->toBe(OtpOutcome::Incorrect);
    expect($otp->verify(to('ada@example.com'), $second))->toBe(OtpOutcome::Verified);
});

it('keeps one person code separate from another', function (): void {
    $otp = app(Otp::class);
    $ada = $otp->issue(to('ada@example.com'));
    $otp->issue(to('grace@example.com'));

    expect($otp->verify(to('grace@example.com'), $ada))->toBe(OtpOutcome::Incorrect);
});

it('clears out codes nobody will use again', function (): void {
    $otp = app(Otp::class);
    $otp->issue(to('ada@example.com'));

    Carbon::setTestNow(Carbon::now()->addDays(30));
    $otp->prune(7);
    Carbon::setTestNow();

    expect(OtpChallenge::query()->count())->toBe(0);
});

describe('asking too often', function (): void {
    it('refuses a second code asked for too soon', function (): void {
        $otp = app(Otp::class);
        $otp->issue(to('ada@example.com'));

        $otp->issue(to('ada@example.com'));
    })->throws(OtpThrottled::class);

    it('says how long the caller has to wait', function (): void {
        config(['keel.auth.otp.throttle' => 90]);

        $otp = app(Otp::class);
        $otp->issue(to('ada@example.com'));

        expect($otp->secondsUntilNextIssue(to('ada@example.com')))->toBeGreaterThan(80);
    });

    it('lets the next code through once the wait is over', function (): void {
        $otp = app(Otp::class);
        $otp->issue(to('ada@example.com'));

        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        expect($otp->issue(to('ada@example.com')))->toBeString();

        Carbon::setTestNow();
    });

    it('holds one person up without holding anybody else up', function (): void {
        $otp = app(Otp::class);
        $otp->issue(to('ada@example.com'));

        expect($otp->issue(to('grace@example.com')))->toBeString();
    });

    it('does not hold anybody up when the product turned the limit off', function (): void {
        config(['keel.auth.otp.throttle' => 0]);

        $otp = app(Otp::class);
        $otp->issue(to('ada@example.com'));

        expect($otp->issue(to('ada@example.com')))->toBeString();
    });
});

describe('a round trip through a provider', function (): void {
    it('accepts back the state it issued', function (): void {
        $state = app(SocialState::class);

        expect($state->claim($state->issue('google')))
            ->toBe(['provider' => 'google', 'actor_id' => null]);
    });

    it('accepts a state once and never again', function (): void {
        $state = app(SocialState::class);
        $issued = $state->issue('google');

        $state->claim($issued);

        expect($state->claim($issued))->toBeNull();
    });

    it('refuses a state nobody issued', function (): void {
        expect(app(SocialState::class)->claim('made-up'))->toBeNull();
    });

    it('remembers who began the round trip, which is what it meant by it', function (): void {
        $state = app(SocialState::class);

        expect($state->claim($state->issue('google', 'user-1'))['actor_id'])->toBe('user-1');
    });

    it('forgets a state the caller took too long to come back with', function (): void {
        config(['keel.auth.social.state_ttl' => 1]);

        $state = app(SocialState::class);
        $issued = $state->issue('google');

        Carbon::setTestNow(Carbon::now()->addMinutes(2));

        expect($state->claim($issued))->toBeNull();

        Carbon::setTestNow();
    });

    it('believes the provider only when it says the address is verified', function (): void {
        $verified = ProviderProfile::make('google', '1', 'ada@example.com', raw: ['email_verified' => true]);
        $older = ProviderProfile::make('google', '1', 'ada@example.com', raw: ['verified_email' => 'true']);
        $silent = ProviderProfile::make('google', '1', 'ada@example.com');

        expect($verified->emailVerified)->toBeTrue();
        expect($older->emailVerified)->toBeTrue();
        expect($silent->emailVerified)->toBeFalse();
    });

    it('keeps no provider token in what it hands back', function (): void {
        $profile = ProviderProfile::make('google', '1', 'ada@example.com', raw: [
            'access_token' => 'secret', 'refresh_token' => 'secret', 'locale' => 'en',
        ]);

        expect($profile->raw)->toBe(['locale' => 'en']);
    });
});

describe('substituting the model', function (): void {
    it('issues and verifies through the model the host substituted', function (): void {
        Keel::useOtpChallengeModel(HostOtpChallenge::class);

        $otp = app(Otp::class);
        $code = $otp->issue(to('ada@example.com'));

        expect(HostOtpChallenge::query()->sole())->toBeInstanceOf(HostOtpChallenge::class);
        expect($otp->verify(to('ada@example.com'), $code))->toBe(OtpOutcome::Verified);
    });

    it('refuses a substitute that is not a sign-in code', function (): void {
        Keel::useOtpChallengeModel(stdClass::class);
    })->throws(InvalidArgumentException::class);
});

describe('a workspace scoped token', function (): void {
    beforeEach(function (): void {
        Schema::create('personal_access_tokens', function ($table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->nullable();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    });

    function mint(array $abilities): PersonalAccessToken
    {
        return PersonalAccessToken::query()->create([
            'tokenable_type' => 'user',
            'tokenable_id' => '01a0b000-0000-7000-8000-000000000001',
            'name' => 'a token',
            'token' => hash('sha256', (string) random_int(1, PHP_INT_MAX)),
            'abilities' => $abilities,
        ]);
    }

    it('writes the workspace its abilities name into a column of its own', function (): void {
        $token = mint(['workspace:01a0b000-0000-7000-8000-00000000000a', 'invoice:view']);

        expect($token->workspace_id)->toBe('01a0b000-0000-7000-8000-00000000000a');
    });

    it('refuses to be written at all when it names no workspace', function (): void {
        mint(['invoice:view']);
    })->throws(UnscopedToken::class);

    it('refuses to be written when it names two', function (): void {
        mint([
            'workspace:01a0b000-0000-7000-8000-00000000000a',
            'workspace:01a0b000-0000-7000-8000-00000000000b',
        ]);
    })->throws(UnscopedToken::class);

    it('refuses a workspace that is not an identifier', function (): void {
        mint(['workspace:all-of-them']);
    })->throws(UnscopedToken::class);

    it('leaves no unscoped row behind after refusing one', function (): void {
        try {
            mint(['invoice:view']);
        } catch (UnscopedToken) {
        }

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('writes the workspace into the column the product renamed', function (): void {
        Schema::table('personal_access_tokens', fn ($table) => $table->renameColumn('workspace_id', 'tenant_id'));
        config(['keel.tenancy.column' => 'tenant_id']);

        $token = mint(['workspace:01a0b000-0000-7000-8000-00000000000a']);

        expect($token->getAttribute('tenant_id'))->toBe('01a0b000-0000-7000-8000-00000000000a');
    });

    it('names a workspace keyed the way the product keys its workspaces', function (string $type, string $id, ?string $read): void {
        config(['keel.tenancy.key_type' => $type]);

        expect(TokenAbilities::workspaceIn([TokenAbilities::forWorkspace($id)]))->toBe($read);
        expect(TokenAbilities::isValid(TokenAbilities::forWorkspace($id)))->toBe($read !== null);
    })->with([
        'a uuid' => ['uuid', '01A0B000-0000-7000-8000-00000000000A', '01a0b000-0000-7000-8000-00000000000a'],
        'a ulid' => ['ulid', '01ARZ3NDEKTSV4RRFFQ69G5FAV', '01arz3ndektsv4rrffq69g5fav'],
        'an integer' => ['int', '42', '42'],
        'a slug' => ['string', 'acme-ltd', 'acme-ltd'],
        'an integer where a uuid is expected' => ['uuid', '42', null],
        'a uuid where an integer is expected' => ['int', '01a0b000-0000-7000-8000-00000000000a', null],
        'zero' => ['int', '0', null],
        'a slug with a colon' => ['string', 'acme:ltd', null],
    ]);
});

describe('announcing', function (): void {
    it('announces a code it issued, without the code in it', function (): void {
        Event::fake([OtpIssued::class]);

        app(Otp::class)->issue(to('ada@example.com'), Channel::Sms);

        Event::assertDispatched(OtpIssued::class, fn (OtpIssued $event): bool => $event->destination->value === 'ada@example.com'
            && $event->channel === Channel::Sms
            && $event->challenge->exists);
    });

    it('announces every attempt with how it went', function (): void {
        Event::fake([OtpAttempted::class]);
        $code = app(Otp::class)->issue(to('ada@example.com'));

        app(Otp::class)->verify(to('ada@example.com'), $code === '123456' ? '654321' : '123456');
        app(Otp::class)->verify(to('ada@example.com'), $code);

        Event::assertDispatched(OtpAttempted::class, fn (OtpAttempted $event): bool => $event->outcome === OtpOutcome::Incorrect);
        Event::assertDispatched(OtpAttempted::class, fn (OtpAttempted $event): bool => $event->outcome === OtpOutcome::Verified);
    });
});

describe('the cache it keeps state in', function (): void {
    it('keeps a provider round trip in the store the product named', function (): void {
        config(['cache.stores.shared' => ['driver' => 'array'], 'keel.cache.store' => 'shared']);
        app()->forgetInstance(SocialState::class);

        $state = app(SocialState::class)->issue('github');
        Cache::store('shared')->flush();

        expect(app(SocialState::class)->claim($state))->toBeNull();
    });
});

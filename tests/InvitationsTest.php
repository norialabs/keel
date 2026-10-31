<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Noria\Keel\Contracts\Courier;
use Noria\Keel\Events\InvitationAccepted;
use Noria\Keel\Events\InvitationCreated;
use Noria\Keel\Events\InvitationRevoked;
use Noria\Keel\Identity\Channel;
use Noria\Keel\Identity\Destination;
use Noria\Keel\Invitations\Invitation;
use Noria\Keel\Invitations\InvitationOutcome;
use Noria\Keel\Invitations\Invitations;
use Noria\Keel\Keel;
use Noria\Keel\Tenancy\Tenancy;
use Noria\Keel\Tests\Fixtures\HostInvitation;
use Noria\Keel\Tests\Fixtures\StubCourier;

function ada(): Destination
{
    return Destination::tryFrom('ada@example.com') ?? throw new RuntimeException('bad fixture');
}

function grace(): Destination
{
    return Destination::tryFrom('grace@example.com') ?? throw new RuntimeException('bad fixture');
}

beforeEach(function (): void {
    StubCourier::$sent = [];
    app()->bind(Courier::class, StubCourier::class);
});

describe('inviting somebody', function (): void {
    it('keeps neither the token nor the address in the clear', function (): void {
        ['token' => $token] = app(Invitations::class)->invite(ada(), 'member');

        $invitation = Invitation::query()->sole();

        expect($invitation->token_hash)->not->toBe($token);
        expect($invitation->destination_hash)->not->toContain('ada@example.com');
        expect($invitation->destination_hint)->toBe('a**@example.com');
    });

    it('hands the token back once, for the message and nowhere else', function (): void {
        ['token' => $token] = app(Invitations::class)->invite(ada(), 'member');

        expect($token)->toHaveLength(64);
    });

    it('carries the role the product asked for', function (): void {
        app(Invitations::class)->invite(ada(), 'billing-admin');

        expect(Invitation::query()->sole()->role)->toBe('billing-admin');
    });

    it('gives the courier the token and lets the product write the words', function (): void {
        app(Invitations::class)->invite(ada(), 'member', context: ['workspace' => 'Acme']);

        expect(StubCourier::$sent)->toHaveCount(1);
        expect(StubCourier::$sent[0]['kind'])->toBe('invitation');
        expect(StubCourier::$sent[0]['context']['workspace'])->toBe('Acme');
    });

    it('shows the address back when the product asked to see it', function (): void {
        config(['keel.invitations.hint' => 'plain']);

        app(Invitations::class)->invite(ada(), 'member');

        $invitation = Invitation::query()->sole();

        expect($invitation->destination_hint)->toBe('ada@example.com')
            ->and($invitation->destination_hash)->not->toContain('ada@example.com');
    });

    it('reaches somebody on whatsapp as readily as by mail', function (): void {
        $phone = Destination::tryFrom('0712345678');

        app(Invitations::class)->invite($phone, 'member', Channel::WhatsApp);

        expect(Invitation::query()->sole()->channel)->toBe('whatsapp');
    });

    it('replaces an outstanding invitation rather than adding a second', function (): void {
        $invitations = app(Invitations::class);
        ['token' => $first] = $invitations->invite(ada(), 'member');
        ['token' => $second] = $invitations->invite(ada(), 'admin');

        expect(Invitation::query()->count())->toBe(1);
        expect($invitations->find($second)->role)->toBe('admin');

        $invitations->find($first);
    })->throws(InvitationOutcome::class);

    it('leaves one person invitation alone when another is invited', function (): void {
        $invitations = app(Invitations::class);
        $invitations->invite(ada(), 'member');
        $invitations->invite(grace(), 'member');

        expect(Invitation::query()->count())->toBe(2);
    });

    it('leaves the invitation one workspace is waiting on when another invites the same person', function (): void {
        $invitations = app(Invitations::class);
        $acme = (string) Str::uuid();
        $globex = (string) Str::uuid();

        ['token' => $first] = $invitations->invite(ada(), 'member', workspaceId: $acme);
        ['token' => $second] = $invitations->invite(ada(), 'admin', workspaceId: $globex);

        expect(Invitation::query()->count())->toBe(2)
            ->and($invitations->find($first)->workspace_id)->toBe($acme)
            ->and($invitations->find($second)->workspace_id)->toBe($globex);
    });

    it('records the workspace it was named for rather than the one in hand', function (): void {
        $elsewhere = (string) Str::uuid();

        app(Invitations::class)->invite(ada(), 'member', workspaceId: $elsewhere);

        expect(Invitation::query()->sole()->workspace_id)->toBe($elsewhere);
    });
});

describe('accepting one', function (): void {
    it('marks it used and hands it back for the product to make a member from', function (): void {
        $invitations = app(Invitations::class);
        ['token' => $token] = $invitations->invite(ada(), 'member');

        $accepted = $invitations->accept($token, ada(), 'user-1');

        expect($accepted->accepted_at)->not->toBeNull();
        expect($accepted->accepted_by)->toBe('user-1');
        expect($accepted->role)->toBe('member');
    });

    it('refuses somebody who is not the person invited', function (): void {
        $invitations = app(Invitations::class);
        ['token' => $token] = $invitations->invite(ada(), 'member');

        $invitations->accept($token, grace());
    })->throws(InvitationOutcome::class, 'different address');

    it('cannot be accepted twice', function (): void {
        $invitations = app(Invitations::class);
        ['token' => $token] = $invitations->invite(ada(), 'member');
        $invitations->accept($token, ada());

        $invitations->accept($token, ada());
    })->throws(InvitationOutcome::class);

    it('refuses a token nobody issued', function (): void {
        app(Invitations::class)->find('made-up-token');
    })->throws(InvitationOutcome::class, 'no longer valid');

    it('refuses one whose deadline has passed', function (): void {
        $invitations = app(Invitations::class);
        ['token' => $token] = $invitations->invite(ada(), 'member');

        Carbon::setTestNow(Carbon::now()->addDays(8));

        try {
            $invitations->accept($token, ada());
        } finally {
            Carbon::setTestNow();
        }
    })->throws(InvitationOutcome::class, 'expired');

    it('takes its deadline from config', function (): void {
        config(['keel.invitations.ttl_days' => 1]);

        app(Invitations::class)->invite(ada(), 'member');

        expect(Invitation::query()->sole()->expires_at->diffInDays(Carbon::now(), absolute: true))
            ->toBeLessThan(2);
    });
});

describe('withdrawing one', function (): void {
    it('stops it being accepted afterwards', function (): void {
        $invitations = app(Invitations::class);
        ['invitation' => $invitation, 'token' => $token] = $invitations->invite(ada(), 'member');

        $invitations->revoke($invitation);

        $invitations->accept($token, ada());
    })->throws(InvitationOutcome::class);

    it('cannot be withdrawn twice', function (): void {
        $invitations = app(Invitations::class);
        ['invitation' => $invitation] = $invitations->invite(ada(), 'member');
        $invitations->revoke($invitation);

        $invitations->revoke($invitation);
    })->throws(InvitationOutcome::class, 'open invitation');
});

describe('housekeeping', function (): void {
    it('clears out invitations nobody will use again', function (): void {
        app(Invitations::class)->invite(ada(), 'member');

        Carbon::setTestNow(Carbon::now()->addDays(60));
        $removed = app(Invitations::class)->prune(30);
        Carbon::setTestNow();

        expect($removed)->toBe(1);
    });

    it('reads through the model the host substituted', function (): void {
        Keel::useInvitationModel(HostInvitation::class);

        app(Invitations::class)->invite(ada(), 'member');

        expect(HostInvitation::query()->sole())->toBeInstanceOf(HostInvitation::class);
    });
});

describe('a product that names things its own way', function (): void {
    it('accepts into the workspace held in the column the product renamed', function (): void {
        remigrate(['keel.tenancy.column' => 'tenant_id']);
        $model = get_class(new class extends Invitation {});
        Keel::useInvitationModel($model);
        $seen = [];
        $model::saved(function () use (&$seen): void {
            $seen[] = app(Tenancy::class)->id();
        });

        $invitations = app(Invitations::class);
        ['token' => $token] = $invitations->invite(ada(), 'member', workspaceId: '01a0b000-0000-7000-8000-00000000000a');
        $seen = [];

        $accepted = $invitations->accept($token, ada(), 'user-1');

        expect($accepted->getAttribute('tenant_id'))->toBe('01a0b000-0000-7000-8000-00000000000a');
        expect($seen)->toBe(['01a0b000-0000-7000-8000-00000000000a']);
    });
});

describe('announcing', function (): void {
    it('announces an invitation when it is sent, accepted and withdrawn', function (): void {
        Event::fake([InvitationCreated::class, InvitationAccepted::class, InvitationRevoked::class]);
        $invitations = app(Invitations::class);

        ['token' => $token] = $invitations->invite(ada(), 'member');
        $invitations->accept($token, ada());
        ['invitation' => $other] = $invitations->invite(grace(), 'member');
        $invitations->revoke($other);

        Event::assertDispatched(InvitationCreated::class, 2);
        Event::assertDispatched(InvitationAccepted::class, fn (InvitationAccepted $event): bool => $event->invitation->accepted_at !== null);
        Event::assertDispatched(InvitationRevoked::class, fn (InvitationRevoked $event): bool => $event->invitation->is($other));
    });
});

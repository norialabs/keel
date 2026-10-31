<?php

declare(strict_types=1);

namespace Noria\Keel\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Noria\Keel\Events\AuditRecorded;
use Noria\Keel\Keel;
use Noria\Keel\Tenancy\Tenancy;

class AuditRecorder
{
    public function __construct(
        private Tenancy $tenancy,
        private RequestContext $context,
    ) {}

    /** @param  array<string, mixed>  $metadata */
    public function record(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?string $reason = null,
    ): ?AuditLog {
        if (! Config::boolean('keel.audit.enabled', true)) {
            return null;
        }

        $actor = Auth::guard($this->guard())->user();
        $actorId = $actor?->getAuthIdentifier();

        $entry = Keel::auditLogModel()::query()->create([
            Config::string('keel.tenancy.column', 'workspace_id') => $this->tenancy->id(),
            'actor_id' => is_scalar($actorId) ? (string) $actorId : null,
            'actor_type' => match (true) {
                $actor instanceof Model => $actor->getMorphClass(),
                $actor !== null => 'user',
                default => 'system',
            },
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip' => $this->context->ip(),
            'user_agent' => $this->context->userAgent(),
            'request_id' => $this->context->id(),
        ]);

        Event::dispatch(new AuditRecorded($entry));

        return $entry;
    }

    private function guard(): ?string
    {
        $guard = Config::get('keel.audit.guard');

        return is_string($guard) && $guard !== '' ? $guard : null;
    }
}

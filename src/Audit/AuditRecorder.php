<?php

declare(strict_types=1);

namespace Noria\Keel\Audit;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
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

        $actorId = Auth::id();

        return Keel::auditLogModel()::query()->create([
            Config::string('keel.tenancy.column', 'workspace_id') => $this->tenancy->id(),
            'actor_id' => $actorId === null ? null : (string) $actorId,
            'actor_type' => $actorId === null ? 'system' : 'user',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip' => $this->context->ip(),
            'user_agent' => $this->context->userAgent(),
            'request_id' => $this->context->id(),
        ]);
    }
}

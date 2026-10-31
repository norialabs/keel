<?php

declare(strict_types=1);

namespace Noria\Keel\Audit;

use Illuminate\Database\Eloquent\Model;
use Noria\Keel\Concerns\AppendOnly;
use Noria\Keel\Concerns\HasConfiguredKey;
use Noria\Keel\Keel;

/**
 * @property string|int $id
 * @property string|null $workspace_id
 * @property string|null $actor_id
 * @property string $actor_type
 * @property string $action
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $reason
 * @property array<string, mixed> $metadata
 */
class AuditLog extends Model
{
    use AppendOnly;
    use HasConfiguredKey;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Keel::table('audit_logs');
    }

    public function getConnectionName(): ?string
    {
        return Keel::connection() ?? parent::getConnectionName();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

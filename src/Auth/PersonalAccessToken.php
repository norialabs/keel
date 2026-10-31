<?php

declare(strict_types=1);

namespace Noria\Keel\Auth;

use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Noria\Keel\Concerns\BelongsToWorkspace;
use Noria\Keel\Concerns\HasConfiguredKey;

/**
 * @property string|int $id
 * @property string|null $workspace_id
 * @property list<string> $abilities
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use BelongsToWorkspace;
    use HasConfiguredKey;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            $abilities = $token->abilities;

            $token->setAttribute(
                Config::string('keel.tenancy.column', 'workspace_id'),
                TokenAbilities::workspaceIn(is_array($abilities) ? $abilities : []) ?? throw new UnscopedToken,
            );
        });
    }
}

<?php

declare(strict_types=1);

namespace Noria\Keel\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Noria\Keel\Tenancy\Tenancy;

/**
 * @property string|null $workspace_id
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::creating(function (Model $model): void {
            $column = Config::string('keel.tenancy.column', 'workspace_id');

            if (blank($model->getAttribute($column))) {
                $workspaceId = app(Tenancy::class)->id();

                if ($workspaceId !== null) {
                    $model->setAttribute($column, $workspaceId);
                }
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where(Config::string('keel.tenancy.column', 'workspace_id'), $workspaceId);
    }
}

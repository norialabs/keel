<?php

declare(strict_types=1);

namespace Noria\Keel\Rbac;

use BackedEnum;
use Illuminate\Support\Facades\Config;
use Noria\Keel\Contracts\PermissionAction;
use Noria\Keel\Contracts\PermissionResource;
use Noria\Keel\Contracts\ScopedPermissionResource;
use RuntimeException;

final class Catalog
{
    /** @return list<PermissionResource> */
    public static function resources(): array
    {
        $cases = [];

        foreach (self::cases('resources', PermissionResource::class) as $case) {
            if ($case instanceof PermissionResource) {
                $cases[] = $case;
            }
        }

        return $cases;
    }

    /** @return list<PermissionAction> */
    public static function actions(): array
    {
        $cases = [];

        foreach (self::cases('actions', PermissionAction::class) as $case) {
            if ($case instanceof PermissionAction) {
                $cases[] = $case;
            }
        }

        return $cases;
    }

    /**
     * @return list<PermissionResource>
     */
    public static function forScope(string|BackedEnum $scope): array
    {
        $wanted = self::scopeKey($scope);
        $scoped = [];

        foreach (self::resources() as $resource) {
            if (! $resource instanceof ScopedPermissionResource || self::scopeKey($resource->scope()) === $wanted) {
                $scoped[] = $resource;
            }
        }

        return $scoped;
    }

    /**
     * A scope may be named by a plain string or by a host's own backed enum.
     */
    public static function scopeKey(string|BackedEnum $scope): string
    {
        return $scope instanceof BackedEnum ? (string) $scope->value : $scope;
    }

    public static function resource(string $value): ?PermissionResource
    {
        foreach (self::resources() as $resource) {
            if ($resource->value === $value) {
                return $resource;
            }
        }

        return null;
    }

    public static function action(string $value): ?PermissionAction
    {
        foreach (self::actions() as $action) {
            if ($action->value === $value) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $contract
     * @return list<BackedEnum>
     */
    private static function cases(string $key, string $contract): array
    {
        $enum = Config::get('keel.rbac.'.$key);

        if (! is_string($enum) || ! enum_exists($enum) || ! is_a($enum, $contract, allow_string: true)) {
            throw new RuntimeException(
                "keel.rbac.{$key} must name a backed enum implementing ".$contract.'.'
            );
        }

        /** @var list<BackedEnum> */
        return $enum::cases();
    }
}

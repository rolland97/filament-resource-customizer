<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\Auth;
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;

abstract class BaseResourcePermissions
{
    abstract public static function getResourceName(): string;

    /**
     * @return array<int, string>
     */
    public static function methods(): array
    {
        return [];
    }

    /**
     * Full authorization gate string for a single method, e.g. "system:ViewPdf:Request".
     */
    public static function permissionKey(string $method): string
    {
        return app(PermissionGateResolver::class)->key($method, static::getResourceName());
    }

    /**
     * Full gate strings for every method in methods().
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_map(
            static fn (string $method): string => static::permissionKey($method),
            static::methods()
        );
    }

    /**
     * Whether the current user holds the gate for the given method.
     */
    public static function can(string $method): bool
    {
        return Auth::user()?->can(static::permissionKey($method)) ?? false;
    }
}

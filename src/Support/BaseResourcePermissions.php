<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\Auth;

abstract class BaseResourcePermissions
{
    abstract public static function getResourceName(): string;

    public static function methods(): array
    {
        return [];
    }

    public static function permissions(): array
    {
        return array_map(
            fn ($method) => "{$method}:".static::getResourceName(),
            static::methods()
        );
    }

    public static function can(string $method): bool
    {
        return Auth::user()?->can("{$method}:".static::getResourceName()) ?? false;
    }
}

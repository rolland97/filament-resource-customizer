<?php

namespace Rolland\FilamentResourceCustomizer\Shield;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;
use RuntimeException;

class ShieldGateResolver implements PermissionGateResolver
{
    public function key(string $method, string $resource): string
    {
        if (! $this->shieldIsAvailable()) {
            throw new RuntimeException(
                'filament-shield is required for permission gate checks. '
                .'Install bezhansalleh/filament-shield, or avoid calling '
                .'BaseResourcePermissions::can()/permissions()/permissionKey().'
            );
        }

        // ShieldConfig (Utils::getConfig()) extends Illuminate\Support\Fluent,
        // whose properties are resolved dynamically via __get() with no
        // @property annotations. PHPStan cannot verify magic property access
        // (see https://phpstan.org/blog/solving-phpstan-access-to-undefined-property),
        // so ArrayAccess (a real, declared method) is used instead of
        // ->permissions->separator / ->permissions->case.
        $permissions = Utils::getConfig()['permissions'];

        // NOTE: bezhansalleh/filament-shield 4.2.0's public API has no
        // panel-prefixing method (Utils::prefixPermissionWithPanel() does not
        // exist in the installed package - confirmed via source inspection).
        // defaultPermissionKeyBuilder() is the full extent of the public key
        // building surface, so the gate key is used as-is.
        return FilamentShield::defaultPermissionKeyBuilder(
            affix: $method,
            separator: (string) $permissions['separator'],
            subject: $resource,
            case: (string) $permissions['case'],
        );
    }

    protected function shieldIsAvailable(): bool
    {
        return class_exists(FilamentShield::class) && class_exists(Utils::class);
    }
}

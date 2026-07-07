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

        $key = FilamentShield::defaultPermissionKeyBuilder(
            affix: $method,
            separator: (string) $permissions['separator'],
            subject: $resource,
            case: (string) $permissions['case'],
        );

        // Panel-prefixing (e.g. "system:ViewPdf:Request") exists only in some
        // filament-shield versions (dev-main); stable 4.2.0 has no such method.
        // Apply it when the installed version supports it so panel-prefixed apps
        // get correct gate strings; otherwise the unprefixed key is already correct.
        $panelPrefixMethod = $this->panelPrefixMethodName();

        if (method_exists(Utils::class, $panelPrefixMethod)) {
            $key = (string) call_user_func([Utils::class, $panelPrefixMethod], $key);
        }

        return $key;
    }

    protected function shieldIsAvailable(): bool
    {
        return class_exists(FilamentShield::class) && class_exists(Utils::class);
    }

    // Isolated behind a method call (rather than a literal string at the
    // call site) so PHPStan infers a plain `string` return type instead of
    // a literal-string, and does not attempt to statically verify the
    // method exists on Utils - which it does not in the installed
    // filament-shield 4.2.0.
    protected function panelPrefixMethodName(): string
    {
        return 'prefixPermissionWithPanel';
    }
}

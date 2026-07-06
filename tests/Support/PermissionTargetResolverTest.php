<?php

use Rolland\FilamentResourceCustomizer\Support\PermissionTargetResolver;

it('nests custom placement path by panel to avoid cross-panel collisions', function () {
    config([
        'filament-resource-customizer.permissions.placement' => 'custom',
        'filament-resource-customizer.permissions.custom_path' => 'app/Filament/Permissions',
        'filament-resource-customizer.permissions.namespace' => null,
    ]);

    $resolver = new PermissionTargetResolver;

    [, $adminPath] = $resolver->resolve(
        base_path('app/Filament/Admin/Resources/Users'),
        'App\\Filament\\Admin\\Resources\\Users',
        'User'
    );
    [, $customerPath] = $resolver->resolve(
        base_path('app/Filament/Customer/Resources/Users'),
        'App\\Filament\\Customer\\Resources\\Users',
        'User'
    );

    expect($adminPath)->toBe(base_path('app/Filament/Permissions/Admin/UserPermissions.php'))
        ->and($customerPath)->toBe(base_path('app/Filament/Permissions/Customer/UserPermissions.php'))
        ->and($adminPath)->not->toBe($customerPath);
});

it('leaves resource placement untouched', function () {
    config(['filament-resource-customizer.permissions.placement' => 'resource']);

    $resolver = new PermissionTargetResolver;

    [$namespace, $path] = $resolver->resolve(
        base_path('app/Filament/Admin/Resources/Users'),
        'App\\Filament\\Admin\\Resources\\Users',
        'User'
    );

    expect($namespace)->toBe('App\\Filament\\Admin\\Resources\\Users')
        ->and($path)->toBe(base_path('app/Filament/Admin/Resources/Users/UserPermissions.php'));
});

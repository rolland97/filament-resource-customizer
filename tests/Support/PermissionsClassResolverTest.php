<?php

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Support\PermissionsClassResolver;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('resolves a custom-placement permissions class from the panel-nested custom path', function () {
    config([
        'filament-resource-customizer.permissions.placement' => 'custom',
        'filament-resource-customizer.permissions.custom_path' => 'app/Filament/Permissions',
        'filament-resource-customizer.permissions.namespace' => null,
    ]);

    $resourceDir = base_path('app/Filament/Admin/Resources/Users');
    File::ensureDirectoryExists($resourceDir);
    File::put($resourceDir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\Admin\\Resources\\Users;\n\nclass UserResource\n{\n}\n");

    $permDir = base_path('app/Filament/Permissions/Admin');
    File::ensureDirectoryExists($permDir);
    File::put($permDir.'/UserPermissions.php', "<?php\n\nnamespace App\\Filament\\Permissions\\Admin;\n\nclass UserPermissions\n{\n}\n");

    $resolver = app(PermissionsClassResolver::class);
    $file = new SplFileInfo($resourceDir.'/UserResource.php');

    expect($resolver->resolveForResourceFile($file, 'App\\Filament\\Admin\\Resources\\Users\\UserResource'))
        ->toBe('App\\Filament\\Permissions\\Admin\\UserPermissions');
});

it('still resolves a same-directory permissions class under default placement', function () {
    config(['filament-resource-customizer.permissions.placement' => 'resource']);

    $resourceDir = base_path('app/Filament/Resources/Users');
    File::ensureDirectoryExists($resourceDir);
    File::put($resourceDir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\Resources\\Users;\n\nclass UserResource\n{\n}\n");
    File::put($resourceDir.'/UserPermissions.php', "<?php\n\nnamespace App\\Filament\\Resources\\Users;\n\nclass UserPermissions\n{\n}\n");

    $resolver = app(PermissionsClassResolver::class);
    $file = new SplFileInfo($resourceDir.'/UserResource.php');

    expect($resolver->resolveForResourceFile($file, 'App\\Filament\\Resources\\Users\\UserResource'))
        ->toBe('App\\Filament\\Resources\\Users\\UserPermissions');
});

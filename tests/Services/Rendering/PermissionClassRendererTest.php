<?php

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\PermissionClassRenderer;

function renderPermissionClass(string $resourceNamespace = 'App\\Filament\\Resources\\Users'): string
{
    $context = new ResourceContext(
        base_path('app/Filament/Resources/Users/UserResource.php'),
        ['resourceNamespace' => $resourceNamespace],
    );

    return app(PermissionClassRenderer::class)->render($context)->contents;
}

it('extends the default BaseResourcePermissions with its import when no base_class is configured', function () {
    $contents = renderPermissionClass();

    expect($contents)
        ->toContain('use Rolland\\FilamentResourceCustomizer\\Support\\BaseResourcePermissions;')
        ->toContain('class UserPermissions extends BaseResourcePermissions');
});

it('extends a configured base class in a different namespace with a use import', function () {
    config(['filament-resource-customizer.permissions.base_class' => \Rolland\FilamentResourceCustomizer\Support\ResourceName::class]);

    $contents = renderPermissionClass();

    expect($contents)
        ->toContain('use Rolland\\FilamentResourceCustomizer\\Support\\ResourceName;')
        ->toContain('class UserPermissions extends ResourceName');
});

it('omits the use import when the configured base class is in the generated namespace', function () {
    // Generated class namespace == base class namespace => no redundant import.
    config(['filament-resource-customizer.permissions.base_class' => \Rolland\FilamentResourceCustomizer\Support\ResourceName::class]);

    $contents = renderPermissionClass('Rolland\\FilamentResourceCustomizer\\Support');

    expect($contents)
        ->not->toContain('use Rolland\\FilamentResourceCustomizer\\Support\\ResourceName;')
        ->toContain('extends ResourceName');
});

it('throws when the configured base class does not exist', function () {
    config(['filament-resource-customizer.permissions.base_class' => 'App\\Does\\Not\\Exist']);

    expect(fn () => renderPermissionClass())
        ->toThrow(InvalidArgumentException::class, 'permissions.base_class');
});

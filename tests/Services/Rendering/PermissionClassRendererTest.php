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

it('emits a use import for a global-namespace configured base class', function () {
    config(['filament-resource-customizer.permissions.base_class' => \ArrayObject::class]);

    $contents = renderPermissionClass();

    expect($contents)
        ->toContain('use ArrayObject;')
        ->toContain('class UserPermissions extends ArrayObject');
});

it('renders the exact default permission class contents byte-for-byte', function () {
    $contents = renderPermissionClass();

    $expected = <<<'PHP'
<?php

namespace App\Filament\Resources\Users;

use Rolland\FilamentResourceCustomizer\Support\BaseResourcePermissions;

class UserPermissions extends BaseResourcePermissions
{
    public static function getResourceName(): string
    {
        return 'User';
    }

    public static function methods(): array
    {
        return [
        ];
    }
}

PHP;

    // rtrim on both sides to avoid asserting on a fiction-prone trailing newline count.
    expect(rtrim($contents))->toBe(rtrim($expected));
});

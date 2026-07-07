<?php

use PhpParser\ParserFactory;
use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\PermissionClassRenderer;
use Rolland\FilamentResourceCustomizer\Support\ResourceName;

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
    config(['filament-resource-customizer.permissions.base_class' => ResourceName::class]);

    $contents = renderPermissionClass();

    expect($contents)
        ->toContain('use Rolland\\FilamentResourceCustomizer\\Support\\ResourceName;')
        ->toContain('class UserPermissions extends ResourceName');
});

it('omits the use import when the configured base class is in the generated namespace', function () {
    // Generated class namespace == base class namespace => no redundant import.
    config(['filament-resource-customizer.permissions.base_class' => ResourceName::class]);

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
    config(['filament-resource-customizer.permissions.base_class' => ArrayObject::class]);

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
    public const VIEW_ANY = 'viewAny';

    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public static function getResourceName(): string
    {
        return 'User';
    }

    public static function methods(): array
    {
        return [
            self::VIEW_ANY,
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
        ];
    }
}
PHP;

    // Normalize line endings (the stub checks out as CRLF on Windows) and rtrim to avoid
    // asserting on a fiction-prone trailing newline count.
    $normalize = fn (string $value): string => rtrim(str_replace("\r\n", "\n", $value));

    expect($normalize($contents))->toBe($normalize($expected));
});

it('generates consts and a populated methods() from shield.default_methods', function () {
    $contents = renderPermissionClass();

    expect($contents)
        ->toContain("public const VIEW_ANY = 'viewAny';")
        ->toContain("public const DELETE = 'delete';")
        ->toContain('self::VIEW_ANY,')
        ->toContain('self::DELETE,');
});

it('generates no consts and an empty methods() when default_methods is empty', function () {
    config(['filament-resource-customizer.shield.default_methods' => []]);

    $contents = renderPermissionClass();

    expect($contents)
        ->not->toContain('public const')
        ->toContain('return [];');
});

it('honors a custom default_methods set with correct const names', function () {
    config(['filament-resource-customizer.shield.default_methods' => ['viewAny', 'publish']]);

    $contents = renderPermissionClass();

    expect($contents)
        ->toContain("public const VIEW_ANY = 'viewAny';")
        ->toContain("public const PUBLISH = 'publish';")
        ->toContain('self::VIEW_ANY,')
        ->toContain('self::PUBLISH,')
        ->not->toContain("public const VIEW = 'view';");
});

it('generates syntactically valid PHP', function () {
    $contents = renderPermissionClass();

    $parser = (new ParserFactory)->createForHostVersion();
    expect(fn () => $parser->parse($contents))->not->toThrow(Throwable::class);
    expect($parser->parse($contents))->not->toBeNull();
});

<?php

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Support\AmbiguousResourceException;
use Rolland\FilamentResourceCustomizer\Support\ResourceLocator;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('throws when the same resource name exists in multiple panels and no panel is given', function () {
    foreach (['Admin', 'Customer'] as $panel) {
        $dir = base_path("app/Filament/{$panel}/Resources/Users");
        File::ensureDirectoryExists($dir);
        File::put($dir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\{$panel}\\Resources\\Users;\n\nclass UserResource\n{\n}\n");
    }

    $locator = app(ResourceLocator::class);

    expect(fn () => $locator->findResourcePath('UserResource'))
        ->toThrow(AmbiguousResourceException::class);
});

it('returns the single match without throwing when the name is unique', function () {
    $dir = base_path('app/Filament/Admin/Resources/Users');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\Admin\\Resources\\Users;\n\nclass UserResource\n{\n}\n");

    $locator = app(ResourceLocator::class);

    // realpath normalizes directory separators so the assertion holds on Windows too,
    // where File::glob returns backslash-separated paths.
    expect(realpath($locator->findResourcePath('UserResource')))
        ->toBe(realpath($dir.'/UserResource.php'));
});

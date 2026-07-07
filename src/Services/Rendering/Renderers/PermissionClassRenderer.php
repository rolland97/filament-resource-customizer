<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;
use Rolland\FilamentResourceCustomizer\Support\BaseResourcePermissions;
use Rolland\FilamentResourceCustomizer\Support\PermissionTargetResolver;

class PermissionClassRenderer
{
    public function __construct(
        protected StubRenderer $stubRenderer,
        protected PermissionTargetResolver $permissionTargetResolver
    ) {}

    public function render(ResourceContext $context): RenderedFile
    {
        [$namespace, $filePath] = $this->permissionTargetResolver->resolve(
            $context->resourceDirectory,
            $context->resourceNamespace,
            $context->resourceName
        );

        $base = $this->baseClassVariables($namespace);

        $contents = $this->stubRenderer->render('permission', [
            'namespace' => $namespace,
            'className' => "{$context->resourceName}Permissions",
            'resourceName' => $context->resourceName,
            'baseClassImport' => $base['import'],
            'baseClassShort' => $base['short'],
        ]);

        return new RenderedFile($filePath, $contents);
    }

    public function resolvePath(ResourceContext $context): string
    {
        return $this->permissionTargetResolver->resolve(
            $context->resourceDirectory,
            $context->resourceNamespace,
            $context->resourceName
        )[1];
    }

    /**
     * @return array{import: string, short: string}
     */
    protected function baseClassVariables(string $generatedNamespace): array
    {
        $default = BaseResourcePermissions::class;
        $baseClass = ltrim((string) config('filament-resource-customizer.permissions.base_class', $default), '\\');

        if ($baseClass === '') {
            $baseClass = $default;
        }

        if ($baseClass !== $default && ! class_exists($baseClass)) {
            throw new InvalidArgumentException(
                "Configured filament-resource-customizer.permissions.base_class [{$baseClass}] does not exist."
            );
        }

        $short = class_basename($baseClass);
        $baseNamespace = str_contains($baseClass, '\\') ? Str::beforeLast($baseClass, '\\') : '';

        $needsImport = $baseNamespace !== '' && $baseNamespace !== ltrim($generatedNamespace, '\\');

        return [
            'import' => $needsImport ? "use {$baseClass};\n\n" : '',
            'short' => $short,
        ];
    }
}

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
        $methodVars = $this->permissionMethodVariables();

        $contents = $this->stubRenderer->render('permission', [
            'namespace' => $namespace,
            'className' => "{$context->resourceName}Permissions",
            'resourceName' => $context->resourceName,
            'baseClassImport' => $base['import'],
            'baseClassShort' => $base['short'],
            'permissionConsts' => $methodVars['consts'],
            'permissionMethods' => $methodVars['methods'],
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

        $needsImport = $baseNamespace !== ltrim($generatedNamespace, '\\');

        return [
            'import' => $needsImport ? "use {$baseClass};\n\n" : '',
            'short' => $short,
        ];
    }

    /**
     * @return array{consts: string, methods: string}
     */
    protected function permissionMethodVariables(): array
    {
        /** @var array<int, mixed> $configured */
        $configured = (array) config('filament-resource-customizer.shield.default_methods', []);

        $methods = array_values(array_filter(
            $configured,
            static fn ($method): bool => is_string($method) && $method !== ''
        ));

        if ($methods === []) {
            return ['consts' => '', 'methods' => ''];
        }

        $consts = '';
        $refs = [];

        foreach ($methods as $method) {
            $constName = Str::of($method)->snake()->upper()->toString();
            $consts .= "    public const {$constName} = '{$method}';\n\n";
            $refs[] = "            self::{$constName},";
        }

        return [
            'consts' => $consts,
            'methods' => "\n".implode("\n", $refs)."\n        ",
        ];
    }
}

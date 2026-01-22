<?php

namespace Rolland\FilamentResourceCustomizer\Services\Shield;

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Support\ClassNameExtractor;
use Rolland\FilamentResourceCustomizer\Support\PermissionsClassResolver;

class ShieldResourceConfigBuilder
{
    public function __construct(
        protected ClassNameExtractor $classNameExtractor,
        protected PermissionsClassResolver $permissionsClassResolver
    ) {}

    public function build(string $resourcesPath): array
    {
        $resourceConfig = $this->buildResourceConfig($resourcesPath);
        $resources = array_merge($this->staticResources(), $resourceConfig);

        ksort($resources);

        return $resources;
    }

    protected function buildResourceConfig(string $resourcesPath): array
    {
        $resources = [];
        $files = File::allFiles($resourcesPath);

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), 'Resource.php')) {
                continue;
            }

            $resourceClass = $this->classNameExtractor->classFromPath($file->getPathname());

            if (! $resourceClass) {
                continue;
            }

            $permissionsClass = $this->permissionsClassResolver->resolveForResourceFile($file, $resourceClass);

            if ($permissionsClass) {
                $resources[$resourceClass] = ltrim($permissionsClass, '\\');
            } else {
                $resources[$resourceClass] = $this->defaultMethods();
            }
        }

        return $resources;
    }

    protected function defaultMethods(): array
    {
        return config('filament-resource-customizer.shield.default_methods', [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
        ]);
    }

    protected function staticResources(): array
    {
        return config('filament-resource-customizer.shield.static_resources', []);
    }
}

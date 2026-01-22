<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResourceLocator
{
    public function __construct(protected ClassNameExtractor $classNameExtractor) {}

    public function findResourcePath(string $resourceName): ?string
    {
        $normalized = $this->normalizeResourceName($resourceName);
        $resourcesPath = config('filament-resource-customizer.resources_path', 'app/Filament/Resources');
        $basePath = base_path($resourcesPath);

        $searchPaths = [
            $basePath."/{$normalized}.php",
            $basePath."/*/{$normalized}.php",
            $basePath."/*/*/{$normalized}.php",
        ];

        foreach ($searchPaths as $pattern) {
            $files = File::glob($pattern);

            if (! empty($files)) {
                return $files[0];
            }
        }

        return null;
    }

    public function extractResourceNamespace(string $resourcePath): string
    {
        $namespace = $this->classNameExtractor->namespaceFromPath($resourcePath);

        if ($namespace) {
            return $namespace;
        }

        return 'App\\Filament\\Resources';
    }

    public function normalizeResourceName(string $resourceName): string
    {
        $resourceName = class_basename($resourceName);

        if (! Str::endsWith($resourceName, 'Resource')) {
            $resourceName .= 'Resource';
        }

        return $resourceName;
    }
}

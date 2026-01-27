<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer;

class ResourceLocator
{
    public function __construct(
        protected ClassNameExtractor $classNameExtractor,
        protected FilamentResourceCustomizer $resourceCustomizer
    ) {}

    public function findResourcePath(string $resourceName, ?string $panel = null): ?string
    {
        $normalized = $this->normalizeResourceName($resourceName);
        $basePaths = $panel
            ? $this->resourceCustomizer->resourcesPathsForPanel($panel)
            : $this->resourceCustomizer->resourcesPaths();

        foreach ($basePaths as $basePath) {
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

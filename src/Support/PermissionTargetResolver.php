<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PermissionTargetResolver
{
    public function resolve(string $resourceDirectory, string $resourceNamespace, string $resourceName): array
    {
        $placement = config('filament-resource-customizer.permissions.placement', 'resource');
        $customPath = config('filament-resource-customizer.permissions.custom_path');
        $configuredNamespace = config('filament-resource-customizer.permissions.namespace');

        $namespace = $resourceNamespace;
        $targetDirectory = $resourceDirectory;

        if ($placement === 'parent') {
            $targetDirectory = dirname($resourceDirectory);
            $namespace = $this->parentNamespace($resourceNamespace);
        }

        if ($placement === 'custom') {
            if (! $customPath) {
                throw new InvalidArgumentException('Custom permission path is not configured.');
            }

            $base = base_path($customPath);
            $panel = $this->panelFromNamespace($resourceNamespace);
            $targetDirectory = $panel ? $base.'/'.$panel : $base;
        }

        if ($configuredNamespace) {
            $namespace = $configuredNamespace;
        }

        return [$namespace, $targetDirectory."/{$resourceName}Permissions.php"];
    }

    protected function parentNamespace(string $namespace): string
    {
        if (! str_contains($namespace, '\\')) {
            return $namespace;
        }

        return Str::beforeLast($namespace, '\\');
    }

    protected function panelFromNamespace(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);
        $filamentIndex = array_search('Filament', $parts, true);

        if ($filamentIndex === false || ! isset($parts[$filamentIndex + 1])) {
            return null;
        }

        $candidate = $parts[$filamentIndex + 1];

        return $candidate === 'Resources' ? null : $candidate;
    }
}

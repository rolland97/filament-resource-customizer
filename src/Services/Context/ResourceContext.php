<?php

namespace Rolland\FilamentResourceCustomizer\Services\Context;

use Illuminate\Support\Str;
use Rolland\FilamentResourceCustomizer\Support\ResourceName;

class ResourceContext
{
    public readonly string $resourceDirectory;

    public readonly string $resourceName;

    public readonly string $pluralName;

    public readonly string $resourceNamespace;

    public readonly string $tableNamespace;

    public function __construct(
        public readonly string $resourcePath,
        public readonly array $components
    ) {
        $this->resourceDirectory = dirname($resourcePath);
        $this->resourceName = $this->extractResourceName();
        $this->pluralName = Str::plural($this->resourceName);
        $this->resourceNamespace = $components['resourceNamespace'] ?? 'App\\Filament\\Resources';
        $this->tableNamespace = $components['tableNamespace'] ?? $this->inferNamespace('Tables');
    }

    protected function extractResourceName(): string
    {
        return ResourceName::withoutSuffix(basename($this->resourcePath, '.php'));
    }

    protected function inferNamespace(string $suffix): string
    {
        $parts = explode('\\', $this->resourceNamespace);

        if ($suffix) {
            $parts[] = $suffix;
        }

        return implode('\\', $parts);
    }
}

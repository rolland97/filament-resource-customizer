<?php

namespace Rolland\FilamentResourceCustomizer;

class FilamentResourceCustomizer
{
    public function config(?string $key = null, mixed $default = null): mixed
    {
        $baseKey = 'filament-resource-customizer';

        if ($key === null) {
            return config($baseKey, $default);
        }

        return config("{$baseKey}.{$key}", $default);
    }

    public function resourcesPath(): string
    {
        return (string) $this->config('resources_path', 'app/Filament/Resources');
    }

    public function stubsPath(): string
    {
        return (string) $this->config('stubs_path', 'stubs/filament-resource-customizer');
    }

    public function permissionsPlacement(): string
    {
        return (string) $this->config('permissions.placement', 'resource');
    }
}

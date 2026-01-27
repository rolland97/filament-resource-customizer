<?php

namespace Rolland\FilamentResourceCustomizer;

use Illuminate\Support\Facades\File;

class FilamentResourceCustomizer
{
    protected const DEFAULT_RESOURCES_PATH = 'app/Filament/Resources';

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
        return (string) $this->config('resources_path', self::DEFAULT_RESOURCES_PATH);
    }

    public function resourcesPaths(): array
    {
        $configured = $this->config('resources_path', self::DEFAULT_RESOURCES_PATH);
        $paths = is_array($configured) ? $configured : [$configured];

        if ($this->shouldAutoDetectPanels()) {
            $paths = array_merge($paths, $this->detectPanelResourcesPaths());
        }

        $paths = array_values(array_unique(array_map(
            fn (string $path) => $this->normalizePath($path),
            array_filter($paths, fn ($path) => is_string($path) && $path !== '')
        )));

        return $paths;
    }

    public function resourcesPathsForPanel(string $panel): array
    {
        $panel = trim($panel);

        if ($panel === '') {
            return $this->resourcesPaths();
        }

        return [base_path("app/Filament/{$panel}/Resources")];
    }

    public function stubsPath(): string
    {
        return (string) $this->config('stubs_path', 'stubs/filament-resource-customizer');
    }

    public function permissionsPlacement(): string
    {
        return (string) $this->config('permissions.placement', 'resource');
    }

    protected function shouldAutoDetectPanels(): bool
    {
        return (bool) $this->config('panels.auto_detect', true);
    }

    protected function detectPanelResourcesPaths(): array
    {
        $panelRoot = base_path('app/Filament');

        if (! File::isDirectory($panelRoot)) {
            return [];
        }

        $paths = [];

        foreach (File::directories($panelRoot) as $panelPath) {
            $resourcesPath = "{$panelPath}/Resources";

            if (File::isDirectory($resourcesPath)) {
                $paths[] = $resourcesPath;
            }
        }

        return $paths;
    }

    protected function normalizePath(string $path): string
    {
        if (str_starts_with($path, base_path()) || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}

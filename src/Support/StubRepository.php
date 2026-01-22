<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

class StubRepository
{
    public function get(string $name): string
    {
        $customStubPath = $this->resolveCustomStubPath($name);

        if ($customStubPath) {
            return File::get($customStubPath);
        }

        $packageStubPath = dirname(__DIR__, 2)."/stubs/filament-resource-customizer/{$name}.stub";

        if (! File::exists($packageStubPath)) {
            throw new RuntimeException("Stub file not found: {$packageStubPath}");
        }

        return File::get($packageStubPath);
    }

    protected function resolveCustomStubPath(string $name): ?string
    {
        $stubsPath = config('filament-resource-customizer.stubs_path');

        if (! $stubsPath) {
            return null;
        }

        $customPath = base_path($stubsPath)."/{$name}.stub";

        if (File::exists($customPath)) {
            return $customPath;
        }

        return null;
    }
}

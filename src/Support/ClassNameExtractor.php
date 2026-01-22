<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\File;

class ClassNameExtractor
{
    public function namespaceFromPath(string $path): ?string
    {
        $content = File::get($path);

        if (preg_match('/namespace\s+([\\w\\\\]+);/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function classFromPath(string $path): ?string
    {
        $namespace = $this->namespaceFromPath($path);

        if (! $namespace) {
            return null;
        }

        return $namespace.'\\'.basename($path, '.php');
    }
}

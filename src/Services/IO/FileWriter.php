<?php

namespace Rolland\FilamentResourceCustomizer\Services\IO;

use Illuminate\Support\Facades\File;

class FileWriter
{
    public function write(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }
}

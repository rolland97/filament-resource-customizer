<?php

namespace Rolland\FilamentResourceCustomizer\Services\Resources;

use Illuminate\Support\Facades\File;

class ResourceTableLocator
{
    public function findTablePath(string $resourcePath): ?string
    {
        $resourceDir = dirname($resourcePath);
        $tablesDir = "{$resourceDir}/Tables";

        if (! File::isDirectory($tablesDir)) {
            return null;
        }

        $tableFiles = File::glob("{$tablesDir}/*Table.php");

        if (! empty($tableFiles)) {
            return $tableFiles[0];
        }

        return null;
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Services\Resources;

use Illuminate\Support\Facades\File;

class CustomizationStateChecker
{
    public function isCustomized(string $resourcePath): bool
    {
        $resourceDir = dirname($resourcePath);

        $customizedFiles = [
            "{$resourceDir}/Tables/*Column.php",
            "{$resourceDir}/Tables/*Filter.php",
            "{$resourceDir}/Tables/*RecordAction.php",
            "{$resourceDir}/Tables/*ToolbarAction.php",
        ];

        foreach ($customizedFiles as $pattern) {
            $files = File::glob($pattern);

            if (! empty($files)) {
                return true;
            }
        }

        return false;
    }
}

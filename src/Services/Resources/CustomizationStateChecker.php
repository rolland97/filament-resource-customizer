<?php

namespace Rolland\FilamentResourceCustomizer\Services\Resources;

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Services\Table\TableAstLoader;
use Rolland\FilamentResourceCustomizer\Services\Table\TableComponentExtractor;

class CustomizationStateChecker
{
    public function __construct(
        protected ResourceTableLocator $tableLocator,
        protected TableAstLoader $astLoader,
        protected TableComponentExtractor $componentExtractor
    ) {}

    public function isCustomized(string $resourcePath): bool
    {
        if ($this->hasGeneratedSiblings($resourcePath)) {
            return true;
        }

        $tablePath = $this->tableLocator->findTablePath($resourcePath);

        if ($tablePath) {
            $ast = $this->astLoader->load($tablePath);

            if ($this->componentExtractor->hasTemplatedComponents($ast)) {
                return true;
            }
        }

        return false;
    }

    protected function hasGeneratedSiblings(string $resourcePath): bool
    {
        $resourceDir = dirname($resourcePath);

        $customizedFiles = [
            "{$resourceDir}/Tables/*Column.php",
            "{$resourceDir}/Tables/*Filter.php",
            "{$resourceDir}/Tables/*RecordAction.php",
            "{$resourceDir}/Tables/*ToolbarAction.php",
        ];

        foreach ($customizedFiles as $pattern) {
            if (! empty(File::glob($pattern))) {
                return true;
            }
        }

        return false;
    }
}

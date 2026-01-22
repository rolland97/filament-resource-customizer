<?php

namespace Rolland\FilamentResourceCustomizer\Services\Generators;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\IO\FileWriter;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\ColumnClassRenderer;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\FilterClassRenderer;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\PermissionClassRenderer;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\RecordActionClassRenderer;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\TableClassRenderer;
use Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers\ToolbarActionClassRenderer;

class CustomizedFileGenerator
{
    protected ResourceContext $context;

    protected array $generatedFiles = [];

    public function __construct(
        string $resourcePath,
        array $components,
        protected FileWriter $fileWriter,
        protected ColumnClassRenderer $columnRenderer,
        protected FilterClassRenderer $filterRenderer,
        protected RecordActionClassRenderer $recordActionRenderer,
        protected ToolbarActionClassRenderer $toolbarActionRenderer,
        protected TableClassRenderer $tableRenderer,
        protected PermissionClassRenderer $permissionRenderer
    ) {
        $this->context = new ResourceContext($resourcePath, $components);
    }

    public function generate(): array
    {
        $this->generateCustomizationFiles();

        if ($this->permissionsEnabled()) {
            $this->generatedFiles[] = $this->generatePermissionFile();
        }

        return $this->generatedFiles;
    }

    public function generateCustomizationOnly(): array
    {
        $this->generateCustomizationFiles();

        return $this->generatedFiles;
    }

    protected function generateCustomizationFiles(): void
    {
        $this->writeRenderedFile($this->columnRenderer->render($this->context));
        $this->writeRenderedFile($this->filterRenderer->render($this->context));
        $this->writeRenderedFile($this->recordActionRenderer->render($this->context));
        $this->writeRenderedFile($this->toolbarActionRenderer->render($this->context));
        $this->writeRenderedFile($this->tableRenderer->render($this->context));
    }

    public function generatePermissionsOnly(): string
    {
        if (! $this->permissionsEnabled()) {
            throw new \RuntimeException('Permissions generation is disabled by configuration.');
        }

        return $this->generatePermissionFile();
    }

    public function resolvePermissionsPath(): string
    {
        if (! $this->permissionsEnabled()) {
            throw new \RuntimeException('Permissions generation is disabled by configuration.');
        }

        return $this->permissionRenderer->resolvePath($this->context);
    }

    protected function permissionsEnabled(): bool
    {
        return (bool) config('filament-resource-customizer.permissions.enabled', true);
    }

    protected function generatePermissionFile(): string
    {
        $file = $this->permissionRenderer->render($this->context);
        $this->writeRenderedFile($file);

        return $file->path;
    }

    protected function writeRenderedFile(RenderedFile $file): void
    {
        $this->fileWriter->write($file->path, $file->contents);
        $this->generatedFiles[] = $file->path;
    }
}

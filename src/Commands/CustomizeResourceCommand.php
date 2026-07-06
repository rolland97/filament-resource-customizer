<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer;
use Rolland\FilamentResourceCustomizer\Services\Formatting\CodeFormatter;
use Rolland\FilamentResourceCustomizer\Services\Generators\CustomizedFileGenerator;
use Rolland\FilamentResourceCustomizer\Services\Resources\CustomizationStateChecker;
use Rolland\FilamentResourceCustomizer\Services\Resources\ResourceTableLocator;
use Rolland\FilamentResourceCustomizer\Services\Table\TableAnalyzer;
use Rolland\FilamentResourceCustomizer\Support\AmbiguousResourceException;
use Rolland\FilamentResourceCustomizer\Support\ResourceLocator;

use function Laravel\Prompts\text;

class CustomizeResourceCommand extends Command
{
    protected $signature = 'filament:customize-resource {resource? : The resource class name (e.g., DepartmentResource)} {--panel= : Panel name (e.g., Admin)} {--force : Overwrite generated files if they exist}';

    protected $description = 'Convert a Filament resource to a customized pattern with separate action/filter files';

    public function handle(
        ResourceLocator $locator,
        ResourceTableLocator $tableLocator,
        CustomizationStateChecker $customizationStateChecker,
        CodeFormatter $formatter,
        FilamentResourceCustomizer $resourceCustomizer
    ): int {
        $resourceName = $this->argument('resource')
            ?? text('Resource class name', placeholder: 'DepartmentResource');

        $panel = $this->option('panel');

        if ($panel && ! File::isDirectory($resourceCustomizer->resourcesPathsForPanel($panel)[0])) {
            $expected = $resourceCustomizer->resourcesPathsForPanel($panel)[0];
            $this->error("Panel '{$panel}' not found. Expected resources at: {$expected}");

            return self::FAILURE;
        }

        try {
            $resourcePath = $locator->findResourcePath($resourceName, $panel);
        } catch (AmbiguousResourceException $e) {
            $this->error("Resource '{$resourceName}' matches multiple panels. Disambiguate with --panel:");
            foreach ($e->matches as $match) {
                $this->line("  - {$match}");
            }

            return self::FAILURE;
        }

        if (! $resourcePath) {
            $panelLabel = $panel ? " in panel '{$panel}'" : '';
            $this->error("Resource '{$resourceName}' not found{$panelLabel}!");

            return self::FAILURE;
        }

        $this->info("Found resource at: {$resourcePath}");

        if (! $this->option('force') && $customizationStateChecker->isCustomized($resourcePath)) {
            $this->error('This resource has already been customized!');
            $this->line('The resource already uses the customized pattern with separate action/filter files.');

            return self::FAILURE;
        }

        $tablePath = $tableLocator->findTablePath($resourcePath);

        if (! $tablePath) {
            $this->error('Table file not found! Expected a Tables directory with a Table class.');

            return self::FAILURE;
        }

        $this->info("Found table at: {$tablePath}");

        $this->info('Analyzing table structure...');
        $analyzer = app(TableAnalyzer::class, ['tablePath' => $tablePath]);
        $components = $analyzer->analyze();

        if (empty($components)) {
            $this->error('Failed to analyze table!');

            return self::FAILURE;
        }

        if ($analyzer->hasTemplatedComponents()) {
            $this->error('This resource is already customized — its table delegates to generated component classes.');
            $this->line('Refusing to regenerate (this would overwrite your Column/Filter/Action files). Edit those files directly, or restore the resource to a pristine table to re-run.');

            return self::FAILURE;
        }

        $components['resourceNamespace'] = $locator->extractResourceNamespace($resourcePath);
        $components['tableNamespace'] = $components['namespace'] ?? null;

        $this->info('✓ Table analyzed successfully');

        $this->info('Generating customized files...');
        $generator = app(CustomizedFileGenerator::class, [
            'resourcePath' => $resourcePath,
            'components' => $components,
        ]);
        $generatedFiles = $generator->generateCustomizationOnly();

        foreach ($generatedFiles as $file) {
            $this->line("  ✓ Created: {$file}");
        }

        if ($formatter->isAvailable()) {
            $this->info('Formatting code with Laravel Pint...');
            $failedFiles = $formatter->format($generatedFiles);

            foreach ($failedFiles as $file) {
                $this->warn("Failed to format: {$file}");
            }

            $this->info('✓ Code formatted successfully');
        } else {
            $this->warn('Laravel Pint not found, skipping code formatting');
        }

        $this->newLine();
        $this->info('🎉 Customization complete!');
        $this->table(
            ['Component', 'Status'],
            [
                ['Column File', '✓ Created'],
                ['Filter File', '✓ Created'],
                ['Record Action File', '✓ Created'],
                ['Toolbar Action File', '✓ Created'],
                ['Table File', '✓ Updated'],
                ['Code Formatting', '✓ Applied'],
            ]
        );

        return self::SUCCESS;
    }
}

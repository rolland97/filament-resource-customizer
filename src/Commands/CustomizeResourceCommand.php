<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Rolland\FilamentResourceCustomizer\Services\Formatting\CodeFormatter;
use Rolland\FilamentResourceCustomizer\Services\Generators\CustomizedFileGenerator;
use Rolland\FilamentResourceCustomizer\Services\Resources\CustomizationStateChecker;
use Rolland\FilamentResourceCustomizer\Services\Resources\ResourceTableLocator;
use Rolland\FilamentResourceCustomizer\Services\Table\TableAnalyzer;
use Rolland\FilamentResourceCustomizer\Support\ResourceLocator;

use function Laravel\Prompts\text;

class CustomizeResourceCommand extends Command
{
    protected $signature = 'filament:customize-resource {resource? : The resource class name (e.g., DepartmentResource)} {--force : Overwrite generated files if they exist}';

    protected $description = 'Convert a Filament resource to a customized pattern with separate action/filter files';

    public function handle(
        ResourceLocator $locator,
        ResourceTableLocator $tableLocator,
        CustomizationStateChecker $customizationStateChecker,
        CodeFormatter $formatter
    ): int {
        $resourceName = $this->argument('resource')
            ?? text('Resource class name', placeholder: 'DepartmentResource');

        $resourcePath = $locator->findResourcePath($resourceName);

        if (! $resourcePath) {
            $this->error("Resource '{$resourceName}' not found!");

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

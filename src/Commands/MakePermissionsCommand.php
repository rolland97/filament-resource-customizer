<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer;
use Rolland\FilamentResourceCustomizer\Services\Generators\CustomizedFileGenerator;
use Rolland\FilamentResourceCustomizer\Support\ResourceLocator;

use function Laravel\Prompts\text;

class MakePermissionsCommand extends Command
{
    protected $signature = 'filament:make-resource-permissions {resource? : The resource class name (e.g., DepartmentResource)} {--panel= : Panel name (e.g., Admin)} {--force : Overwrite the permissions file if it exists}';

    protected $description = 'Generate a permissions class for a Filament resource';

    public function handle(ResourceLocator $locator, FilamentResourceCustomizer $resourceCustomizer): int
    {
        $resourceName = $this->argument('resource')
            ?? text('Resource class name', placeholder: 'DepartmentResource');

        $panel = $this->option('panel');

        if ($panel && ! File::isDirectory($resourceCustomizer->resourcesPathsForPanel($panel)[0])) {
            $this->error("Panel '{$panel}' not found. Expected resources at: app/Filament/{$panel}/Resources");

            return self::FAILURE;
        }

        $resourcePath = $locator->findResourcePath($resourceName, $panel);

        if (! $resourcePath) {
            $panelLabel = $panel ? " in panel '{$panel}'" : '';
            $this->error("Resource '{$resourceName}' not found{$panelLabel}!");

            return self::FAILURE;
        }

        $resourceNamespace = $locator->extractResourceNamespace($resourcePath);
        $generator = app(CustomizedFileGenerator::class, [
            'resourcePath' => $resourcePath,
            'components' => [
                'resourceNamespace' => $resourceNamespace,
            ],
        ]);

        $permissionPath = $generator->resolvePermissionsPath();

        if (File::exists($permissionPath) && ! $this->option('force')) {
            $this->error("Permissions file already exists at: {$permissionPath}");
            $this->line('Use --force to overwrite it.');

            return self::FAILURE;
        }

        $permissionPath = $generator->generatePermissionsOnly();

        $this->info("✓ Created permissions file: {$permissionPath}");

        return self::SUCCESS;
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Services\Generators\CustomizedFileGenerator;
use Rolland\FilamentResourceCustomizer\Support\ResourceLocator;

use function Laravel\Prompts\text;

class MakePermissionsCommand extends Command
{
    protected $signature = 'filament:make-resource-permissions {resource? : The resource class name (e.g., DepartmentResource)} {--force : Overwrite the permissions file if it exists}';

    protected $description = 'Generate a permissions class for a Filament resource';

    public function handle(ResourceLocator $locator): int
    {
        $resourceName = $this->argument('resource')
            ?? text('Resource class name', placeholder: 'DepartmentResource');

        $resourcePath = $locator->findResourcePath($resourceName);

        if (! $resourcePath) {
            $this->error("Resource '{$resourceName}' not found!");

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

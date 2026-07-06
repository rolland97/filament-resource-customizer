<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer;
use Rolland\FilamentResourceCustomizer\Services\Shield\ShieldResourceConfigBuilder;
use Rolland\FilamentResourceCustomizer\Support\ShieldConfigUpdater;
use RuntimeException;

class ShieldConfigCommand extends Command
{
    protected $signature = 'filament:shield-config {--path= : Custom config path} {--panel= : Panel name (e.g., Admin)} {--merge : Merge with existing resources instead of replacing} {--no-merge : Do not merge with existing resources}';

    protected $description = 'Generate Filament Shield resources configuration based on resources and permissions classes';

    public function handle(ShieldResourceConfigBuilder $builder, ShieldConfigUpdater $updater): int
    {
        $configPath = $this->option('path') ?: base_path('config/filament-shield.php');

        if (! File::exists($configPath)) {
            $this->error("Config file not found at: {$configPath}");

            return self::FAILURE;
        }

        $panel = $this->option('panel');
        $resourceCustomizer = app(FilamentResourceCustomizer::class);
        $resourcesPaths = $panel
            ? $resourceCustomizer->resourcesPathsForPanel($panel)
            : $resourceCustomizer->resourcesPaths();

        if ($panel && ! File::isDirectory($resourcesPaths[0])) {
            $this->error("Panel '{$panel}' not found. Expected resources at: {$resourcesPaths[0]}");

            return self::FAILURE;
        }
        $resourcesPaths = array_values(array_filter($resourcesPaths, fn (string $path) => File::isDirectory($path)));

        if ($resourcesPaths === []) {
            $this->error('No resources directories found. Check filament-resource-customizer.resources_path or panels.auto_detect.');

            return self::FAILURE;
        }

        $resources = $builder->buildForPaths($resourcesPaths);

        try {
            $updater->updateResources($configPath, $resources, $this->resolveMergeOption());
        } catch (RuntimeException $e) {
            $this->error("Failed to update Shield config: {$e->getMessage()}");
            $this->line('Ensure the config returns an array with a `resources.manage` entry (publish the Filament Shield config first).');

            return self::FAILURE;
        }

        $this->info('Filament Shield config updated.');

        return self::SUCCESS;
    }

    protected function resolveMergeOption(): bool
    {
        if ($this->input->hasParameterOption('--no-merge')) {
            return false;
        }

        if ($this->input->hasParameterOption('--merge')) {
            return true;
        }

        return (bool) config('filament-resource-customizer.shield.merge', false);
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Services\Shield\ShieldResourceConfigBuilder;
use Rolland\FilamentResourceCustomizer\Support\ShieldConfigUpdater;

class ShieldConfigCommand extends Command
{
    protected $signature = 'filament:shield-config {--path= : Custom config path} {--merge : Merge with existing resources instead of replacing} {--no-merge : Do not merge with existing resources}';

    protected $description = 'Generate Filament Shield resources configuration based on resources and permissions classes';

    public function handle(ShieldResourceConfigBuilder $builder, ShieldConfigUpdater $updater): int
    {
        $configPath = $this->option('path') ?: base_path('config/filament-shield.php');

        if (! File::exists($configPath)) {
            $this->error("Config file not found at: {$configPath}");

            return self::FAILURE;
        }

        $resourcesPath = base_path(config('filament-resource-customizer.resources_path', 'app/Filament/Resources'));

        if (! File::isDirectory($resourcesPath)) {
            $this->error("Resources directory not found at: {$resourcesPath}");

            return self::FAILURE;
        }

        $resources = $builder->build($resourcesPath);
        $updater->updateResources($configPath, $resources, $this->resolveMergeOption());

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

<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\text;

class CustomizeResourceAllCommand extends Command
{
    protected $signature = 'filament:customize-resource-all {resource? : The resource class name (e.g., DepartmentResource)} {--force : Overwrite generated files if they exist} {--path= : Custom config path} {--merge : Merge with existing resources instead of replacing} {--no-merge : Do not merge with existing resources}';

    protected $description = 'Customize a resource, generate permissions, and update Filament Shield config';

    public function handle(): int
    {
        $resourceName = $this->argument('resource')
            ?? text('Resource class name', placeholder: 'DepartmentResource');

        $statuses = [];

        $statuses[] = $this->call('filament:customize-resource', [
            'resource' => $resourceName,
            '--force' => (bool) $this->option('force'),
        ]);

        if (config('filament-resource-customizer.permissions.enabled', true)) {
            $statuses[] = $this->call('filament:make-resource-permissions', [
                'resource' => $resourceName,
                '--force' => (bool) $this->option('force'),
            ]);
        } else {
            $this->warn('Permissions generation is disabled in the config.');
        }

        $configPath = $this->option('path') ?: base_path('config/filament-shield.php');

        if (! File::exists($configPath)) {
            $this->warn("Filament Shield config not found at: {$configPath}");

            return self::FAILURE;
        }

        $shieldOptions = [
            '--path' => $this->option('path'),
        ];

        if ($this->input->hasParameterOption('--merge')) {
            $shieldOptions['--merge'] = true;
        }

        if ($this->input->hasParameterOption('--no-merge')) {
            $shieldOptions['--no-merge'] = true;
        }

        $statuses[] = $this->call('filament:shield-config', $shieldOptions);

        foreach ($statuses as $status) {
            if ($status !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}

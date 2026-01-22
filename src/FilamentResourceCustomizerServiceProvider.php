<?php

namespace Rolland\FilamentResourceCustomizer;

use Illuminate\Filesystem\Filesystem;
use Rolland\FilamentResourceCustomizer\Commands\CustomizeResourceAllCommand;
use Rolland\FilamentResourceCustomizer\Commands\CustomizeResourceCommand;
use Rolland\FilamentResourceCustomizer\Commands\MakePermissionsCommand;
use Rolland\FilamentResourceCustomizer\Commands\ShieldConfigCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentResourceCustomizerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('filament-resource-customizer')
            ->hasConfigFile()
            ->hasCommands($this->getCommands());
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->singleton('filament-resource-customizer', fn () => new FilamentResourceCustomizer);
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        if (! app()->runningInConsole()) {
            return;
        }

        $packagePath = dirname(__DIR__, 2);
        $stubsPath = $packagePath.'/stubs/filament-resource-customizer';

        if (! is_dir($stubsPath)) {
            return;
        }

        foreach (app(Filesystem::class)->files($stubsPath) as $file) {
            $this->publishes([
                $file->getRealPath() => base_path('stubs/filament-resource-customizer/'.$file->getFilename()),
            ], 'filament-resource-customizer-stubs');
        }
    }

    protected function getCommands(): array
    {
        return [
            CustomizeResourceCommand::class,
            CustomizeResourceAllCommand::class,
            MakePermissionsCommand::class,
            ShieldConfigCommand::class,
        ];
    }
}

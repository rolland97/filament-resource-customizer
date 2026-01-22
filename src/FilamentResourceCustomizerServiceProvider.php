<?php

namespace Rolland\FilamentResourceCustomizer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Rolland\FilamentResourceCustomizer\Commands\FilamentResourceCustomizerCommand;

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
            ->hasViews()
            ->hasMigration('create_filament_resource_customizer_table')
            ->hasCommand(FilamentResourceCustomizerCommand::class);
    }
}

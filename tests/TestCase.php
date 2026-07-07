<?php

namespace Rolland\FilamentResourceCustomizer\Tests;

use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Rolland\\FilamentResourceCustomizer\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            FilamentResourceCustomizerServiceProvider::class,
        ];

        if (class_exists(FilamentShieldServiceProvider::class)) {
            $providers[] = FilamentShieldServiceProvider::class;
        }

        return $providers;
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }
}

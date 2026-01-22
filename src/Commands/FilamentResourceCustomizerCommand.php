<?php

namespace Rolland\FilamentResourceCustomizer\Commands;

use Illuminate\Console\Command;

class FilamentResourceCustomizerCommand extends Command
{
    public $signature = 'filament-resource-customizer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

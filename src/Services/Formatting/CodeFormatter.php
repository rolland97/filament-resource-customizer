<?php

namespace Rolland\FilamentResourceCustomizer\Services\Formatting;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class CodeFormatter
{
    public function isAvailable(): bool
    {
        return File::exists(base_path('vendor/bin/pint'));
    }

    public function format(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $pintPath = base_path('vendor/bin/pint');

        if (! File::exists($pintPath)) {
            return [];
        }

        $failed = [];

        foreach ($files as $file) {
            $process = new Process(
                [$pintPath, $file],
                base_path(),
                null,
                null,
                60
            );

            $process->run();

            if (! $process->isSuccessful()) {
                $failed[] = $file;
            }
        }

        return $failed;
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Str;

class ResourceName
{
    public static function withoutSuffix(string $name): string
    {
        $base = class_basename($name);

        return Str::endsWith($base, 'Resource')
            ? substr($base, 0, -strlen('Resource'))
            : $base;
    }
}

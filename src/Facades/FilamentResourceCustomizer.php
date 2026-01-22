<?php

namespace Rolland\FilamentResourceCustomizer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer
 */
class FilamentResourceCustomizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer::class;
    }
}

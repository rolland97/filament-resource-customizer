<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use RuntimeException;

class AmbiguousResourceException extends RuntimeException
{
    /**
     * @param  list<string>  $matches
     */
    public function __construct(public readonly array $matches)
    {
        parent::__construct('Resource name matches multiple panels: '.implode(', ', $matches));
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering;

class RenderedFile
{
    public function __construct(
        public string $path,
        public string $contents
    ) {}
}

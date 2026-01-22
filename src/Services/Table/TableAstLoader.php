<?php

namespace Rolland\FilamentResourceCustomizer\Services\Table;

use Illuminate\Support\Facades\File;
use PhpParser\ParserFactory;

class TableAstLoader
{
    public function load(string $tablePath): array
    {
        $code = File::get($tablePath);
        $parser = (new ParserFactory)->createForHostVersion();

        return $parser->parse($code) ?? [];
    }
}

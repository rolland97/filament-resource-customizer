<?php

namespace Rolland\FilamentResourceCustomizer\Services\Formatting;

class ColumnArrayFormatter
{
    public function format(array $columns): string
    {
        if (empty($columns)) {
            return '[]';
        }

        $formatted = "[\n";

        foreach ($columns as $column) {
            $code = $column['code'];
            $formatted .= "            {$code},\n";
        }

        $formatted .= '        ]';

        return $formatted;
    }
}

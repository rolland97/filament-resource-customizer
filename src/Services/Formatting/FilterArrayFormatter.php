<?php

namespace Rolland\FilamentResourceCustomizer\Services\Formatting;

class FilterArrayFormatter
{
    public function format(array $filters): string
    {
        if (empty($filters)) {
            return '[]';
        }

        $formatted = "[\n";

        foreach ($filters as $filter) {
            $code = $filter['code'];
            $formatted .= "            {$code},\n";
        }

        $formatted .= '        ]';

        return $formatted;
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Services\Formatting;

class ActionArrayFormatter
{
    public function format(array $actions): string
    {
        if (empty($actions)) {
            return '';
        }

        $formatted = '';

        foreach ($actions as $action) {
            $code = $action['code'];
            $formatted .= "            {$code},\n";
        }

        return rtrim($formatted);
    }
}

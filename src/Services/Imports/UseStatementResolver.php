<?php

namespace Rolland\FilamentResourceCustomizer\Services\Imports;

class UseStatementResolver
{
    public function resolveColumnUses(array $columns): array
    {
        $uses = [];

        foreach ($columns as $column) {
            $code = $column['code'];

            if (str_contains($code, 'TextColumn::')) {
                $uses[] = 'Filament\\Tables\\Columns\\TextColumn';
            }

            if (str_contains($code, 'IconColumn::')) {
                $uses[] = 'Filament\\Tables\\Columns\\IconColumn';
            }

            if (str_contains($code, 'ImageColumn::')) {
                $uses[] = 'Filament\\Tables\\Columns\\ImageColumn';
            }

            if (str_contains($code, 'ColorColumn::')) {
                $uses[] = 'Filament\\Tables\\Columns\\ColorColumn';
            }
        }

        return array_values(array_unique($uses));
    }

    public function resolveActionUses(array $actions, array $availableUses): array
    {
        if (empty($actions) || empty($availableUses)) {
            return $this->extractQualifiedActionUses($actions, []);
        }

        $useMap = [];

        foreach ($availableUses as $use) {
            $useMap[class_basename($use)] = $use;
        }

        $uses = [];

        foreach ($actions as $action) {
            $code = $action['code'];

            if (! preg_match_all('/([A-Z][A-Za-z0-9_]*)::/', $code, $matches)) {
                continue;
            }

            foreach ($matches[1] as $className) {
                if (isset($useMap[$className])) {
                    $uses[] = $useMap[$className];
                }
            }
        }

        return $this->extractQualifiedActionUses($actions, $uses);
    }

    public function replaceQualifiedActionClasses(array $actions, array $uses): array
    {
        if (empty($actions) || empty($uses)) {
            return $actions;
        }

        $replacementMap = [];

        foreach ($uses as $use) {
            $baseName = class_basename($use);
            $replacementMap['\\'.ltrim($use, '\\')] = $baseName;
            $replacementMap[ltrim($use, '\\')] = $baseName;
        }

        foreach ($actions as $index => $action) {
            $code = $action['code'];
            $actions[$index]['code'] = str_replace(array_keys($replacementMap), array_values($replacementMap), $code);
        }

        return $actions;
    }

    public function formatUseStatements(array $uses): string
    {
        return implode("\n", array_map(fn ($use) => "use {$use};", $uses));
    }

    protected function extractQualifiedActionUses(array $actions, array $uses): array
    {
        foreach ($actions as $action) {
            $code = $action['code'];

            if (! preg_match_all('/\\\\?[A-Z][A-Za-z0-9_]*(?:\\\\[A-Za-z0-9_]+)+(?=::)/', $code, $matches)) {
                continue;
            }

            foreach ($matches[0] as $qualifiedClass) {
                $uses[] = ltrim($qualifiedClass, '\\');
            }
        }

        return array_values(array_unique($uses));
    }
}

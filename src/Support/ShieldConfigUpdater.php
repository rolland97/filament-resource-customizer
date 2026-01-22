<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;
use RuntimeException;

class ShieldConfigUpdater
{
    public function updateResources(string $configPath, array $resources, bool $merge): void
    {
        $code = file_get_contents($configPath);

        if ($code === false) {
            throw new RuntimeException("Unable to read config file: {$configPath}");
        }

        $parser = (new ParserFactory)->createForHostVersion();

        $ast = $parser->parse($code);

        if (! is_array($ast)) {
            throw new RuntimeException('Config file could not be parsed.');
        }

        $return = $this->findReturn($ast);

        if (! $return || ! $return->expr instanceof Array_) {
            throw new RuntimeException('Config file must return an array.');
        }

        $rootArray = $return->expr;
        $resourcesItem = $this->findResourcesItem($rootArray);

        if (! $resourcesItem || ! $resourcesItem->value instanceof Array_) {
            $resourcesArray = new Array_([], ['kind' => Array_::KIND_SHORT]);
            $rootArray->items[] = new ArrayItem($resourcesArray, new String_('resources'));
        } else {
            $resourcesArray = $resourcesItem->value;
        }
        $manageItem = $this->findManageItem($resourcesArray);

        if (! $manageItem || ! $manageItem->value instanceof Array_) {
            $manageArray = new Array_([], ['kind' => Array_::KIND_SHORT]);
            $resourcesArray->items[] = new ArrayItem($manageArray, new String_('manage'));
        } else {
            $manageArray = $manageItem->value;
        }

        $this->ensureResourcesKey($resourcesArray, 'subject', new String_('model'));
        $this->ensureResourcesKey($resourcesArray, 'exclude', new Array_([], ['kind' => Array_::KIND_SHORT]));
        $existingManage = $this->arrayItemsToMap($manageArray);
        $merged = $merge ? array_merge($existingManage, $resources) : $resources;

        $formatted = $this->formatArray($merged, $this->detectIndent($code, $manageArray));
        $start = $manageArray->getAttribute('startFilePos');
        $end = $manageArray->getAttribute('endFilePos');

        if ($start === null || $end === null) {
            throw new RuntimeException('Unable to determine array position for formatting.');
        }

        $updated = substr($code, 0, $start).$formatted.substr($code, $end + 1);
        file_put_contents($configPath, $updated);
    }

    protected function findReturn(array $ast): ?Return_
    {
        foreach ($ast as $node) {
            if ($node instanceof Return_) {
                return $node;
            }
        }

        return null;
    }

    protected function findResourcesItem(Array_ $array): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === 'resources') {
                return $item;
            }
        }

        return null;
    }

    protected function findManageItem(Array_ $array): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === 'manage') {
                return $item;
            }
        }

        return null;
    }

    protected function ensureResourcesKey(Array_ $array, string $key, Expr $value): void
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            if ($item->key->value === $key) {
                return;
            }
        }

        $array->items[] = new ArrayItem($value, new String_($key));
    }

    protected function extractKey(ArrayItem $item): ?string
    {
        $key = $item->key;

        if ($key instanceof String_) {
            return $key->value;
        }

        if ($key instanceof ClassConstFetch && $key->class instanceof FullyQualified) {
            return $key->class->toString();
        }

        return null;
    }

    protected function arrayItemsToMap(Array_ $array): array
    {
        $map = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $key = $this->extractKey($item);

            if ($key === null) {
                continue;
            }

            if ($item->value instanceof StaticCall && $item->value->class instanceof FullyQualified) {
                $map[$key] = $item->value->class->toString();

                continue;
            }

            if ($item->value instanceof Array_) {
                $map[$key] = $this->arrayToList($item->value);
            }
        }

        return $map;
    }

    protected function arrayToList(Array_ $array): array
    {
        $items = [];

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->value instanceof String_) {
                $items[] = $item->value->value;
            }
        }

        return $items;
    }

    protected function detectIndent(string $code, Array_ $array): string
    {
        $start = $array->getAttribute('startFilePos');

        if ($start === null) {
            return '';
        }

        $lineStart = strrpos(substr($code, 0, $start), "\n");

        if ($lineStart === false) {
            return '';
        }

        $line = substr($code, $lineStart + 1, $start - $lineStart - 1);

        return preg_match('/^\s+/', $line, $matches) ? $matches[0] : '';
    }

    protected function formatArray(array $items, string $indent): string
    {
        $lines = ['['];

        foreach ($items as $key => $value) {
            $formattedKey = $this->formatKey($key);
            $formattedValue = $this->formatValue($value, $indent.'    ');
            $lines[] = $indent.'    '.$formattedKey.' => '.$formattedValue.',';
        }

        $lines[] = $indent.']';

        return implode("\n", $lines);
    }

    protected function formatKey(string $key): string
    {
        if (str_contains($key, '\\')) {
            return '\\'.ltrim($key, '\\').'::class';
        }

        return "'".str_replace("'", "\\'", $key)."'";
    }

    protected function formatValue(mixed $value, string $indent): string
    {
        if (is_string($value)) {
            return '\\'.ltrim($value, '\\').'::methods()';
        }

        $lines = ['['];

        foreach ((array) $value as $method) {
            $lines[] = $indent."'".str_replace("'", "\\'", (string) $method)."',";
        }

        $lines[] = substr($indent, 0, -4).']';

        return implode("\n", $lines);
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Services\Table;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\PrettyPrinter\Standard;

class TableComponentExtractor
{
    protected Standard $printer;

    public function __construct()
    {
        $this->printer = new Standard;
    }

    public function extractNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                return $node->name->toString();
            }
        }

        return null;
    }

    public function extractClassName(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        return $stmt->name->toString();
                    }
                }
            }
        }

        return null;
    }

    public function extractColumns(array $ast): ?array
    {
        return $this->extractConfigureArguments($ast, 'columns');
    }

    public function extractFilters(array $ast): ?array
    {
        return $this->extractConfigureArguments($ast, 'filters');
    }

    public function extractRecordActions(array $ast): ?array
    {
        return $this->extractConfigureArguments($ast, 'recordActions');
    }

    public function extractToolbarActions(array $ast): ?array
    {
        return $this->extractConfigureArguments($ast, 'toolbarActions');
    }

    public function extractUseStatements(array $ast): array
    {
        $uses = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            $uses[] = $use->name->toString();
                        }
                    }
                }
            }
        }

        return $uses;
    }

    public function hasTemplatedComponents(array $ast): bool
    {
        $configureMethod = $this->findMethod($ast, 'configure');

        if (! $configureMethod) {
            return false;
        }

        foreach (['columns', 'filters', 'recordActions', 'toolbarActions'] as $methodName) {
            $argument = $this->componentArgument($configureMethod, $methodName);

            if ($argument !== null && ! $argument instanceof Node\Expr\Array_) {
                return true;
            }
        }

        return false;
    }

    protected function componentArgument(ClassMethod $method, string $methodName): ?Node
    {
        foreach ($method->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                $argument = $this->findMethodCallArgumentNode($stmt->expr, $methodName);

                if ($argument !== null) {
                    return $argument;
                }
            }
        }

        return null;
    }

    protected function findMethodCallArgumentNode(?Node $node, string $methodName): ?Node
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if ($node->name instanceof Node\Identifier && $node->name->toString() === $methodName && isset($node->args[0])) {
            return $node->args[0]->value;
        }

        return $this->findMethodCallArgumentNode($node->var, $methodName);
    }

    protected function extractConfigureArguments(array $ast, string $methodName): ?array
    {
        $configureMethod = $this->findMethod($ast, 'configure');

        if (! $configureMethod) {
            return null;
        }

        return $this->extractMethodCallArguments($configureMethod, $methodName);
    }

    protected function extractMethodCallArguments(ClassMethod $method, string $methodName): ?array
    {
        foreach ($method->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                $args = $this->findMethodCallArguments($stmt->expr, $methodName);

                if ($args !== null) {
                    return $args;
                }
            }
        }

        return null;
    }

    protected function findMethodCallArguments(?Node $node, string $methodName): ?array
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if ($node->name instanceof Node\Identifier && $node->name->toString() === $methodName) {
            if (isset($node->args[0])) {
                return $this->extractArrayElements($node->args[0]->value);
            }
        }

        return $this->findMethodCallArguments($node->var, $methodName);
    }

    protected function extractArrayElements(Node $arrayNode): array
    {
        if (! $arrayNode instanceof Node\Expr\Array_) {
            return [];
        }

        $elements = [];

        foreach ($arrayNode->items as $item) {
            if ($item !== null && $item->value !== null) {
                $elements[] = [
                    'code' => $this->printer->prettyPrintExpr($item->value),
                    'type' => $this->determineElementType($item->value),
                    'node' => $item->value,
                ];
            }
        }

        return $elements;
    }

    protected function determineElementType(Node $node): string
    {
        if ($node instanceof Node\Expr\StaticCall) {
            $className = $node->class instanceof Node\Name ? $node->class->toString() : '';

            if (str_contains($className, 'Column') || str_contains($className, 'TextColumn') || str_contains($className, 'IconColumn')) {
                return 'column';
            }

            if (str_contains($className, 'Filter')) {
                return 'filter';
            }

            if (str_contains($className, 'Action')) {
                return 'action';
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            return 'chained_component';
        }

        return 'unknown';
    }

    protected function findMethod(array $ast, string $methodName): ?ClassMethod
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        foreach ($stmt->stmts as $classStmt) {
                            if ($classStmt instanceof ClassMethod && $classStmt->name->toString() === $methodName) {
                                return $classStmt;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}

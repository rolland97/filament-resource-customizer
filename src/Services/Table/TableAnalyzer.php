<?php

namespace Rolland\FilamentResourceCustomizer\Services\Table;

class TableAnalyzer
{
    protected array $ast;

    public function __construct(
        protected string $tablePath,
        protected TableAstLoader $astLoader,
        protected TableComponentExtractor $componentExtractor
    ) {
        $this->ast = $this->astLoader->load($this->tablePath);
    }

    public function analyze(): array
    {
        return [
            'namespace' => $this->componentExtractor->extractNamespace($this->ast),
            'className' => $this->componentExtractor->extractClassName($this->ast),
            'columns' => $this->componentExtractor->extractColumns($this->ast),
            'filters' => $this->componentExtractor->extractFilters($this->ast),
            'recordActions' => $this->componentExtractor->extractRecordActions($this->ast),
            'toolbarActions' => $this->componentExtractor->extractToolbarActions($this->ast),
            'uses' => $this->componentExtractor->extractUseStatements($this->ast),
        ];
    }
}

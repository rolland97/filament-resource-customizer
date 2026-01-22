<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Formatting\ColumnArrayFormatter;
use Rolland\FilamentResourceCustomizer\Services\Imports\UseStatementResolver;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;

class ColumnClassRenderer
{
    public function __construct(
        protected StubRenderer $stubRenderer,
        protected ColumnArrayFormatter $columnFormatter,
        protected UseStatementResolver $useStatementResolver
    ) {}

    public function render(ResourceContext $context): RenderedFile
    {
        $columns = $context->components['columns'] ?? [];
        $columnCode = $this->columnFormatter->format($columns);
        $uses = $this->useStatementResolver->resolveColumnUses($columns);
        $useStatements = $this->useStatementResolver->formatUseStatements($uses);

        $contents = $this->stubRenderer->render('column', [
            'namespace' => $context->tableNamespace,
            'uses' => $useStatements,
            'className' => "{$context->pluralName}Column",
            'columns' => $columnCode,
        ]);

        $path = "{$context->resourceDirectory}/Tables/{$context->pluralName}Column.php";

        return new RenderedFile($path, $contents);
    }
}

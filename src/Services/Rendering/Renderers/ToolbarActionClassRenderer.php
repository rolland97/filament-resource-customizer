<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Formatting\ActionArrayFormatter;
use Rolland\FilamentResourceCustomizer\Services\Imports\UseStatementResolver;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;

class ToolbarActionClassRenderer
{
    public function __construct(
        protected StubRenderer $stubRenderer,
        protected ActionArrayFormatter $actionFormatter,
        protected UseStatementResolver $useStatementResolver
    ) {}

    public function render(ResourceContext $context): RenderedFile
    {
        $toolbarActions = $context->components['toolbarActions'] ?? [];
        $uses = $this->useStatementResolver->resolveActionUses($toolbarActions, $context->components['uses'] ?? []);
        $useStatements = $this->useStatementResolver->formatUseStatements($uses);
        $toolbarActions = $this->useStatementResolver->replaceQualifiedActionClasses($toolbarActions, $uses);
        $actionCode = $this->actionFormatter->format($toolbarActions);

        $contents = $this->stubRenderer->render('toolbar-action', [
            'namespace' => $context->tableNamespace,
            'uses' => $useStatements,
            'className' => "{$context->pluralName}ToolbarAction",
            'actions' => $actionCode,
        ]);

        $path = "{$context->resourceDirectory}/Tables/{$context->pluralName}ToolbarAction.php";

        return new RenderedFile($path, $contents);
    }
}

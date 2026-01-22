<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Formatting\ActionArrayFormatter;
use Rolland\FilamentResourceCustomizer\Services\Imports\UseStatementResolver;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;

class RecordActionClassRenderer
{
    public function __construct(
        protected StubRenderer $stubRenderer,
        protected ActionArrayFormatter $actionFormatter,
        protected UseStatementResolver $useStatementResolver
    ) {}

    public function render(ResourceContext $context): RenderedFile
    {
        $recordActions = $context->components['recordActions'] ?? [];
        $uses = $this->useStatementResolver->resolveActionUses($recordActions, $context->components['uses'] ?? []);
        $useStatements = $this->useStatementResolver->formatUseStatements($uses);
        $recordActions = $this->useStatementResolver->replaceQualifiedActionClasses($recordActions, $uses);
        $actionCode = $this->actionFormatter->format($recordActions);

        $contents = $this->stubRenderer->render('record-action', [
            'namespace' => $context->tableNamespace,
            'uses' => $useStatements,
            'className' => "{$context->pluralName}RecordAction",
            'actions' => $actionCode,
        ]);

        $path = "{$context->resourceDirectory}/Tables/{$context->pluralName}RecordAction.php";

        return new RenderedFile($path, $contents);
    }
}

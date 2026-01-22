<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;

class TableClassRenderer
{
    public function __construct(protected StubRenderer $stubRenderer) {}

    public function render(ResourceContext $context): RenderedFile
    {
        $contents = $this->stubRenderer->render('table', [
            'namespace' => $context->tableNamespace,
            'className' => "{$context->pluralName}Table",
            'resourceName' => $context->pluralName,
        ]);

        $path = "{$context->resourceDirectory}/Tables/{$context->pluralName}Table.php";

        return new RenderedFile($path, $contents);
    }
}

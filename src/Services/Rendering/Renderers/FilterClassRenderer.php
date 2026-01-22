<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Formatting\FilterArrayFormatter;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;

class FilterClassRenderer
{
    public function __construct(
        protected StubRenderer $stubRenderer,
        protected FilterArrayFormatter $filterFormatter
    ) {}

    public function render(ResourceContext $context): RenderedFile
    {
        $filters = $context->components['filters'] ?? [];
        $getMethod = $this->filterFormatter->format($filters);

        $contents = $this->stubRenderer->render('filter', [
            'namespace' => $context->tableNamespace,
            'className' => "{$context->pluralName}Filter",
            'getMethod' => $getMethod,
            'methods' => '',
        ]);

        $path = "{$context->resourceDirectory}/Tables/{$context->pluralName}Filter.php";

        return new RenderedFile($path, $contents);
    }
}

<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering\Renderers;

use Rolland\FilamentResourceCustomizer\Services\Context\ResourceContext;
use Rolland\FilamentResourceCustomizer\Services\Rendering\RenderedFile;
use Rolland\FilamentResourceCustomizer\Services\Rendering\StubRenderer;
use Rolland\FilamentResourceCustomizer\Support\PermissionTargetResolver;

class PermissionClassRenderer
{
    public function __construct(
        protected StubRenderer $stubRenderer,
        protected PermissionTargetResolver $permissionTargetResolver
    ) {}

    public function render(ResourceContext $context): RenderedFile
    {
        [$namespace, $filePath] = $this->permissionTargetResolver->resolve(
            $context->resourceDirectory,
            $context->resourceNamespace,
            $context->resourceName
        );

        $contents = $this->stubRenderer->render('permission', [
            'namespace' => $namespace,
            'className' => "{$context->resourceName}Permissions",
            'resourceName' => $context->resourceName,
        ]);

        return new RenderedFile($filePath, $contents);
    }

    public function resolvePath(ResourceContext $context): string
    {
        return $this->permissionTargetResolver->resolve(
            $context->resourceDirectory,
            $context->resourceNamespace,
            $context->resourceName
        )[1];
    }
}

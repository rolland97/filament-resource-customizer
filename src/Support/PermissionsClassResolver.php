<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\File;
use SplFileInfo;

class PermissionsClassResolver
{
    public function __construct(
        protected ClassNameExtractor $classNameExtractor,
        protected PermissionTargetResolver $targetResolver
    ) {}

    public function resolveForResourceFile(SplFileInfo $resourceFile, string $resourceClass): ?string
    {
        $resourceName = ResourceName::withoutSuffix($resourceClass);
        $resourceDirectory = $resourceFile->getPath();
        $resourceNamespace = $this->classNameExtractor->namespaceFromPath($resourceFile->getPathname()) ?? '';

        [, $targetPath] = $this->targetResolver->resolve($resourceDirectory, $resourceNamespace, $resourceName);

        if (File::exists($targetPath)) {
            return $this->classNameExtractor->classFromPath($targetPath);
        }

        // Backward-compatible fallbacks: same directory, then immediate parent.
        $permissionsClassName = $resourceName.'Permissions';

        foreach ([$resourceDirectory, dirname($resourceDirectory)] as $dir) {
            $candidate = $dir.'/'.$permissionsClassName.'.php';

            if (File::exists($candidate)) {
                return $this->classNameExtractor->classFromPath($candidate);
            }
        }

        return null;
    }
}

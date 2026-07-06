<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\File;
use SplFileInfo;
use Rolland\FilamentResourceCustomizer\Support\ResourceName;

class PermissionsClassResolver
{
    public function __construct(protected ClassNameExtractor $classNameExtractor) {}

    public function resolveForResourceFile(SplFileInfo $resourceFile, string $resourceClass): ?string
    {
        $baseName = ResourceName::withoutSuffix($resourceClass);
        $permissionsClassName = $baseName.'Permissions';

        $currentDir = $resourceFile->getPath();
        $candidate = $currentDir.'/'.$permissionsClassName.'.php';

        if (File::exists($candidate)) {
            return $this->classNameExtractor->classFromPath($candidate);
        }

        $parentDir = dirname($currentDir);
        $parentCandidate = $parentDir.'/'.$permissionsClassName.'.php';

        if (File::exists($parentCandidate)) {
            return $this->classNameExtractor->classFromPath($parentCandidate);
        }

        return null;
    }
}

# Package Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix seven audited correctness bugs in the resource-customizer package and bump version constraints (PHP 8.5, documented Filament v4/v5 support).

**Architecture:** Pure text/AST codegen package (no Filament loaded at runtime). Fixes touch the AST extractor, command guards, permission-class location resolution, the Shield config updater, and version metadata. Two shared helpers are extracted to DRY up name normalization and permission-class location.

**Tech Stack:** PHP 8.3–8.5, Laravel 11/12 (via `illuminate/contracts`), `nikic/php-parser`, Pest 4, PHPStan level 5, testbench 10.

## Global Constraints

- Every fix is test-first (TDD): write the failing test, run it red, implement, run it green, commit.
- Existing suite (12 tests) must stay green after every task: `composer test`.
- PHPStan level 5 must stay clean after every task: `composer analyse`.
- Package must NOT add `filament/*` to `require` — it does not load Filament at runtime.
- Follow existing code style; run `composer format` (Pint) before committing if it touches formatting.
- Commit after each task with a Conventional Commit message.
- `docs/` is gitignored — do not commit plan/spec files.

---

### Task 1: Fix #7 — resource-name suffix stripping (shared `ResourceName` helper)

**Files:**
- Create: `src/Support/ResourceName.php`
- Modify: `src/Services/Context/ResourceContext.php:30-35`
- Modify: `src/Support/PermissionsClassResolver.php:14`
- Test: `tests/Support/ResourceNameTest.php`

**Interfaces:**
- Produces: `Rolland\FilamentResourceCustomizer\Support\ResourceName::withoutSuffix(string $name): string` — returns the class basename with a single trailing `Resource` removed; returns the basename unchanged if it does not end in `Resource`.

- [ ] **Step 1: Write the failing test**

Create `tests/Support/ResourceNameTest.php`:

```php
<?php

use Rolland\FilamentResourceCustomizer\Support\ResourceName;

it('strips only the trailing Resource suffix', function () {
    expect(ResourceName::withoutSuffix('UserResource'))->toBe('User')
        ->and(ResourceName::withoutSuffix('HumanResourceResource'))->toBe('HumanResource')
        ->and(ResourceName::withoutSuffix('ResourceResource'))->toBe('Resource')
        ->and(ResourceName::withoutSuffix('Invoice'))->toBe('Invoice')
        ->and(ResourceName::withoutSuffix('App\\Filament\\Resources\\UserResource'))->toBe('User');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Support/ResourceNameTest.php`
Expected: FAIL — class `ResourceName` not found.

- [ ] **Step 3: Create the helper**

Create `src/Support/ResourceName.php`:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Str;

class ResourceName
{
    public static function withoutSuffix(string $name): string
    {
        $base = class_basename($name);

        return Str::endsWith($base, 'Resource')
            ? substr($base, 0, -strlen('Resource'))
            : $base;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Support/ResourceNameTest.php`
Expected: PASS.

- [ ] **Step 5: Wire the helper into the two call sites**

In `src/Services/Context/ResourceContext.php`, replace `extractResourceName()` (lines 30-35):

```php
    protected function extractResourceName(): string
    {
        return ResourceName::withoutSuffix(basename($this->resourcePath, '.php'));
    }
```

Add the import near the top (after the existing `use Illuminate\Support\Str;`):

```php
use Rolland\FilamentResourceCustomizer\Support\ResourceName;
```

In `src/Support/PermissionsClassResolver.php`, replace line 14:

```php
        $baseName = ResourceName::withoutSuffix($resourceClass);
```

Add the import (after `use SplFileInfo;`):

```php
use Rolland\FilamentResourceCustomizer\Support\ResourceName;
```

- [ ] **Step 6: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green, no PHPStan errors.

- [ ] **Step 7: Commit**

```bash
git add src/Support/ResourceName.php src/Services/Context/ResourceContext.php src/Support/PermissionsClassResolver.php tests/Support/ResourceNameTest.php
git commit -m "fix: strip only trailing Resource suffix from resource names"
```

---

### Task 2: Fix #5 — `--panel` path honors configured template

**Files:**
- Modify: `config/filament-resource-customizer.php:7-9`
- Modify: `src/FilamentResourceCustomizer.php:44-53`
- Modify: `src/Commands/CustomizeResourceCommand.php:35-36`
- Modify: `src/Commands/MakePermissionsCommand.php:26-27`
- Modify: `src/Commands/ShieldConfigCommand.php:33-34`
- Test: `tests/FilamentResourceCustomizerTest.php`

**Interfaces:**
- Produces: `FilamentResourceCustomizer::resourcesPathsForPanel(string $panel): array` — now builds the path from config key `panels.path_template` (default `app/Filament/{panel}/Resources`) with `{panel}` substituted, wrapped in `base_path()`.

- [ ] **Step 1: Write the failing test**

Create `tests/FilamentResourceCustomizerTest.php`:

```php
<?php

use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer;

it('derives panel resource path from configured template', function () {
    config(['filament-resource-customizer.panels.path_template' => 'src/Panels/{panel}/Resources']);

    $customizer = new FilamentResourceCustomizer;

    expect($customizer->resourcesPathsForPanel('Admin'))
        ->toBe([base_path('src/Panels/Admin/Resources')]);
});

it('falls back to the default filament panel path template', function () {
    $customizer = new FilamentResourceCustomizer;

    expect($customizer->resourcesPathsForPanel('Admin'))
        ->toBe([base_path('app/Filament/Admin/Resources')]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/FilamentResourceCustomizerTest.php`
Expected: FAIL — first test gets the hardcoded `app/Filament/Admin/Resources`.

- [ ] **Step 3: Add the config key**

In `config/filament-resource-customizer.php`, replace the `panels` block (lines 7-9):

```php
    'panels' => [
        'auto_detect' => true,
        'path_template' => 'app/Filament/{panel}/Resources',
    ],
```

- [ ] **Step 4: Implement template resolution**

In `src/FilamentResourceCustomizer.php`, replace `resourcesPathsForPanel()` (lines 44-53):

```php
    public function resourcesPathsForPanel(string $panel): array
    {
        $panel = trim($panel);

        if ($panel === '') {
            return $this->resourcesPaths();
        }

        $template = (string) $this->config('panels.path_template', 'app/Filament/{panel}/Resources');

        return [base_path(str_replace('{panel}', $panel, $template))];
    }
```

- [ ] **Step 5: Fix the three command error messages to show the resolved path**

In `src/Commands/CustomizeResourceCommand.php`, replace lines 35-36:

```php
        if ($panel && ! File::isDirectory($resourceCustomizer->resourcesPathsForPanel($panel)[0])) {
            $expected = $resourceCustomizer->resourcesPathsForPanel($panel)[0];
            $this->error("Panel '{$panel}' not found. Expected resources at: {$expected}");
```

In `src/Commands/MakePermissionsCommand.php`, replace lines 26-27 with the same two lines.

In `src/Commands/ShieldConfigCommand.php`, replace lines 33-34:

```php
        if ($panel && ! File::isDirectory($resourcesPaths[0])) {
            $this->error("Panel '{$panel}' not found. Expected resources at: {$resourcesPaths[0]}");
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/FilamentResourceCustomizerTest.php`
Expected: PASS.

- [ ] **Step 7: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add config/filament-resource-customizer.php src/FilamentResourceCustomizer.php src/Commands/CustomizeResourceCommand.php src/Commands/MakePermissionsCommand.php src/Commands/ShieldConfigCommand.php tests/FilamentResourceCustomizerTest.php
git commit -m "fix: derive per-panel resource path from configured template"
```

---

### Task 3: Fix #6 — Shield config missing `manage` fails cleanly instead of crashing

**Files:**
- Modify: `src/Commands/ShieldConfigCommand.php:46-50`
- Test: `tests/Commands/ShieldConfigCommandTest.php` (append)

**Interfaces:**
- Consumes: `ShieldConfigUpdater::updateResources()` throws `RuntimeException` when the target config lacks a spliceable `resources.manage` array.
- Produces: `ShieldConfigCommand::handle()` catches `RuntimeException` → prints error → returns `self::FAILURE` (no unhandled exception).

- [ ] **Step 1: Write the failing test**

Append to `tests/Commands/ShieldConfigCommandTest.php`:

```php
it('fails cleanly when the shield config has no manage array', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Organisations');
    File::ensureDirectoryExists($resourceDir);

    File::put($resourceDir.'/OrganisationResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationResource
{
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'navigation' => true,
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath])
        ->assertExitCode(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commands/ShieldConfigCommandTest.php --filter="fails cleanly when the shield config has no manage array"`
Expected: FAIL — an unhandled `RuntimeException` ("Unable to determine array position for formatting.") bubbles up instead of exit code 1.

- [ ] **Step 3: Catch the exception in the command**

In `src/Commands/ShieldConfigCommand.php`, add the import after the existing `use` block:

```php
use RuntimeException;
```

Replace lines 46-50 (the `buildForPaths` + `updateResources` + success block):

```php
        $resources = $builder->buildForPaths($resourcesPaths);

        try {
            $updater->updateResources($configPath, $resources, $this->resolveMergeOption());
        } catch (RuntimeException $e) {
            $this->error("Failed to update Shield config: {$e->getMessage()}");
            $this->line('Ensure the config returns an array with a `resources.manage` entry (publish the Filament Shield config first).');

            return self::FAILURE;
        }

        $this->info('Filament Shield config updated.');

        return self::SUCCESS;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commands/ShieldConfigCommandTest.php`
Expected: PASS (all shield-config tests including the new one).

- [ ] **Step 5: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Commands/ShieldConfigCommand.php tests/Commands/ShieldConfigCommandTest.php
git commit -m "fix: shield-config fails cleanly when config lacks a manage array"
```

---

### Task 4: Fix #4 — ambiguous cross-panel resource name errors instead of silently picking first

**Files:**
- Create: `src/Support/AmbiguousResourceException.php`
- Modify: `src/Support/ResourceLocator.php:16-40`
- Modify: `src/Commands/CustomizeResourceCommand.php:41-48`
- Modify: `src/Commands/MakePermissionsCommand.php:32-39`
- Test: `tests/Support/ResourceLocatorTest.php`

**Interfaces:**
- Produces: `AmbiguousResourceException extends RuntimeException` with `public readonly array $matches` (list of absolute file paths).
- Produces: `ResourceLocator::findResourcePath(string $resourceName, ?string $panel = null): ?string` — when `$panel` is null and the name matches in more than one location, throws `AmbiguousResourceException`; otherwise unchanged (returns first match or null).

- [ ] **Step 1: Write the failing test**

Create `tests/Support/ResourceLocatorTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Support\AmbiguousResourceException;
use Rolland\FilamentResourceCustomizer\Support\ResourceLocator;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('throws when the same resource name exists in multiple panels and no panel is given', function () {
    foreach (['Admin', 'Customer'] as $panel) {
        $dir = base_path("app/Filament/{$panel}/Resources/Users");
        File::ensureDirectoryExists($dir);
        File::put($dir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\{$panel}\\Resources\\Users;\n\nclass UserResource\n{\n}\n");
    }

    $locator = app(ResourceLocator::class);

    expect(fn () => $locator->findResourcePath('UserResource'))
        ->toThrow(AmbiguousResourceException::class);
});

it('returns the single match without throwing when the name is unique', function () {
    $dir = base_path('app/Filament/Admin/Resources/Users');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\Admin\\Resources\\Users;\n\nclass UserResource\n{\n}\n");

    $locator = app(ResourceLocator::class);

    expect($locator->findResourcePath('UserResource'))->toBe($dir.'/UserResource.php');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Support/ResourceLocatorTest.php`
Expected: FAIL — first test does not throw (locator returns the alphabetically-first match).

- [ ] **Step 3: Create the exception**

Create `src/Support/AmbiguousResourceException.php`:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use RuntimeException;

class AmbiguousResourceException extends RuntimeException
{
    /**
     * @param  list<string>  $matches
     */
    public function __construct(public readonly array $matches)
    {
        parent::__construct('Resource name matches multiple panels: '.implode(', ', $matches));
    }
}
```

- [ ] **Step 4: Collect all matches and throw on ambiguity**

In `src/Support/ResourceLocator.php`, replace `findResourcePath()` (lines 16-40):

```php
    public function findResourcePath(string $resourceName, ?string $panel = null): ?string
    {
        $normalized = $this->normalizeResourceName($resourceName);
        $basePaths = $panel
            ? $this->resourceCustomizer->resourcesPathsForPanel($panel)
            : $this->resourceCustomizer->resourcesPaths();

        $matches = [];

        foreach ($basePaths as $basePath) {
            $searchPaths = [
                $basePath."/{$normalized}.php",
                $basePath."/*/{$normalized}.php",
                $basePath."/*/*/{$normalized}.php",
            ];

            foreach ($searchPaths as $pattern) {
                foreach (File::glob($pattern) as $file) {
                    $matches[$file] = $file;
                }
            }
        }

        $matches = array_values($matches);

        if ($matches === []) {
            return null;
        }

        if ($panel === null && count($matches) > 1) {
            throw new AmbiguousResourceException($matches);
        }

        return $matches[0];
    }
```

(The `$matches[$file] = $file` keying de-duplicates a path that matches more than one glob pattern.)

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Support/ResourceLocatorTest.php`
Expected: PASS.

- [ ] **Step 6: Catch the exception in both commands**

In `src/Commands/CustomizeResourceCommand.php`, replace lines 41-48:

```php
        try {
            $resourcePath = $locator->findResourcePath($resourceName, $panel);
        } catch (\Rolland\FilamentResourceCustomizer\Support\AmbiguousResourceException $e) {
            $this->error("Resource '{$resourceName}' matches multiple panels. Disambiguate with --panel:");
            foreach ($e->matches as $match) {
                $this->line("  - {$match}");
            }

            return self::FAILURE;
        }

        if (! $resourcePath) {
            $panelLabel = $panel ? " in panel '{$panel}'" : '';
            $this->error("Resource '{$resourceName}' not found{$panelLabel}!");

            return self::FAILURE;
        }
```

In `src/Commands/MakePermissionsCommand.php`, replace lines 32-39 with the identical `try/catch` + not-found block above.

- [ ] **Step 7: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add src/Support/AmbiguousResourceException.php src/Support/ResourceLocator.php src/Commands/CustomizeResourceCommand.php src/Commands/MakePermissionsCommand.php tests/Support/ResourceLocatorTest.php
git commit -m "fix: error on ambiguous cross-panel resource name instead of silent pick"
```

---

### Task 5: Fix #2 + #3 — custom-placement panel scoping (shared via `PermissionTargetResolver`)

**Files:**
- Modify: `src/Support/PermissionTargetResolver.php:10-46`
- Modify: `src/Support/PermissionsClassResolver.php` (whole class)
- Test: `tests/Support/PermissionTargetResolverTest.php`
- Test: `tests/Support/PermissionsClassResolverTest.php`

**Interfaces:**
- Consumes: `PermissionTargetResolver::resolve(string $resourceDirectory, string $resourceNamespace, string $resourceName): array` returning `[$namespace, $filePath]`.
- Produces (behavior change): when `placement === 'custom'` and a panel segment is derivable from `$resourceNamespace` (the segment immediately after `Filament`), the file path is nested as `{custom_path}/{Panel}/{resourceName}Permissions.php`. Non-custom placements are unchanged.
- Produces: `PermissionsClassResolver::resolveForResourceFile(SplFileInfo $resourceFile, string $resourceClass): ?string` now delegates path computation to `PermissionTargetResolver` (single source of truth), so `placement=custom` classes are found.

- [ ] **Step 1: Write the failing tests**

Create `tests/Support/PermissionTargetResolverTest.php`:

```php
<?php

use Rolland\FilamentResourceCustomizer\Support\PermissionTargetResolver;

it('nests custom placement path by panel to avoid cross-panel collisions', function () {
    config([
        'filament-resource-customizer.permissions.placement' => 'custom',
        'filament-resource-customizer.permissions.custom_path' => 'app/Filament/Permissions',
        'filament-resource-customizer.permissions.namespace' => null,
    ]);

    $resolver = new PermissionTargetResolver;

    [, $adminPath] = $resolver->resolve(
        base_path('app/Filament/Admin/Resources/Users'),
        'App\\Filament\\Admin\\Resources\\Users',
        'User'
    );
    [, $customerPath] = $resolver->resolve(
        base_path('app/Filament/Customer/Resources/Users'),
        'App\\Filament\\Customer\\Resources\\Users',
        'User'
    );

    expect($adminPath)->toBe(base_path('app/Filament/Permissions/Admin/UserPermissions.php'))
        ->and($customerPath)->toBe(base_path('app/Filament/Permissions/Customer/UserPermissions.php'))
        ->and($adminPath)->not->toBe($customerPath);
});

it('leaves resource placement untouched', function () {
    config(['filament-resource-customizer.permissions.placement' => 'resource']);

    $resolver = new PermissionTargetResolver;

    [$namespace, $path] = $resolver->resolve(
        base_path('app/Filament/Admin/Resources/Users'),
        'App\\Filament\\Admin\\Resources\\Users',
        'User'
    );

    expect($namespace)->toBe('App\\Filament\\Admin\\Resources\\Users')
        ->and($path)->toBe(base_path('app/Filament/Admin/Resources/Users/UserPermissions.php'));
});
```

Create `tests/Support/PermissionsClassResolverTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Support\PermissionsClassResolver;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('resolves a custom-placement permissions class from the panel-nested custom path', function () {
    config([
        'filament-resource-customizer.permissions.placement' => 'custom',
        'filament-resource-customizer.permissions.custom_path' => 'app/Filament/Permissions',
        'filament-resource-customizer.permissions.namespace' => null,
    ]);

    $resourceDir = base_path('app/Filament/Admin/Resources/Users');
    File::ensureDirectoryExists($resourceDir);
    File::put($resourceDir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\Admin\\Resources\\Users;\n\nclass UserResource\n{\n}\n");

    $permDir = base_path('app/Filament/Permissions/Admin');
    File::ensureDirectoryExists($permDir);
    File::put($permDir.'/UserPermissions.php', "<?php\n\nnamespace App\\Filament\\Permissions\\Admin;\n\nclass UserPermissions\n{\n}\n");

    $resolver = app(PermissionsClassResolver::class);
    $file = new SplFileInfo($resourceDir.'/UserResource.php');

    expect($resolver->resolveForResourceFile($file, 'App\\Filament\\Admin\\Resources\\Users\\UserResource'))
        ->toBe('App\\Filament\\Permissions\\Admin\\UserPermissions');
});

it('still resolves a same-directory permissions class under default placement', function () {
    config(['filament-resource-customizer.permissions.placement' => 'resource']);

    $resourceDir = base_path('app/Filament/Resources/Users');
    File::ensureDirectoryExists($resourceDir);
    File::put($resourceDir.'/UserResource.php', "<?php\n\nnamespace App\\Filament\\Resources\\Users;\n\nclass UserResource\n{\n}\n");
    File::put($resourceDir.'/UserPermissions.php', "<?php\n\nnamespace App\\Filament\\Resources\\Users;\n\nclass UserPermissions\n{\n}\n");

    $resolver = app(PermissionsClassResolver::class);
    $file = new SplFileInfo($resourceDir.'/UserResource.php');

    expect($resolver->resolveForResourceFile($file, 'App\\Filament\\Resources\\Users\\UserResource'))
        ->toBe('App\\Filament\\Resources\\Users\\UserPermissions');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Support/PermissionTargetResolverTest.php tests/Support/PermissionsClassResolverTest.php`
Expected: FAIL — custom path is not panel-nested; resolver does not consult the custom path.

- [ ] **Step 3: Add panel nesting to `PermissionTargetResolver`**

In `src/Support/PermissionTargetResolver.php`, replace the `custom` branch (lines 24-30) and add a helper. The full class body becomes:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PermissionTargetResolver
{
    public function resolve(string $resourceDirectory, string $resourceNamespace, string $resourceName): array
    {
        $placement = config('filament-resource-customizer.permissions.placement', 'resource');
        $customPath = config('filament-resource-customizer.permissions.custom_path');
        $configuredNamespace = config('filament-resource-customizer.permissions.namespace');

        $namespace = $resourceNamespace;
        $targetDirectory = $resourceDirectory;

        if ($placement === 'parent') {
            $targetDirectory = dirname($resourceDirectory);
            $namespace = $this->parentNamespace($resourceNamespace);
        }

        if ($placement === 'custom') {
            if (! $customPath) {
                throw new InvalidArgumentException('Custom permission path is not configured.');
            }

            $base = base_path($customPath);
            $panel = $this->panelFromNamespace($resourceNamespace);
            $targetDirectory = $panel ? $base.'/'.$panel : $base;
        }

        if ($configuredNamespace) {
            $namespace = $configuredNamespace;
        }

        return [$namespace, $targetDirectory."/{$resourceName}Permissions.php"];
    }

    protected function parentNamespace(string $namespace): string
    {
        if (! str_contains($namespace, '\\')) {
            return $namespace;
        }

        return Str::beforeLast($namespace, '\\');
    }

    protected function panelFromNamespace(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);
        $filamentIndex = array_search('Filament', $parts, true);

        if ($filamentIndex === false || ! isset($parts[$filamentIndex + 1])) {
            return null;
        }

        $candidate = $parts[$filamentIndex + 1];

        return $candidate === 'Resources' ? null : $candidate;
    }
}
```

(The `Resources` guard means the default single-panel layout `App\Filament\Resources\...`, which has no panel segment, does not get spuriously nested.)

- [ ] **Step 4: Route `PermissionsClassResolver` through the shared resolver**

Replace the whole of `src/Support/PermissionsClassResolver.php`:

```php
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
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Support/PermissionTargetResolverTest.php tests/Support/PermissionsClassResolverTest.php`
Expected: PASS.

- [ ] **Step 6: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green (existing shield-config tests still pass — default placement path equals the resource directory).

- [ ] **Step 7: Commit**

```bash
git add src/Support/PermissionTargetResolver.php src/Support/PermissionsClassResolver.php tests/Support/PermissionTargetResolverTest.php tests/Support/PermissionsClassResolverTest.php
git commit -m "fix: panel-scope custom permission placement and resolve it in shield-config"
```

---

### Task 6: Fix #1 — re-running customize on an already-customized resource refuses instead of wiping data

**Files:**
- Modify: `src/Services/Table/TableComponentExtractor.php` (add detection methods)
- Modify: `src/Services/Table/TableAnalyzer.php` (expose detection)
- Modify: `src/Services/Resources/CustomizationStateChecker.php` (AST-based detection)
- Modify: `src/Commands/CustomizeResourceCommand.php` (guard before generation)
- Test: `tests/Commands/CustomizeResourceRerunTest.php`

**Interfaces:**
- Produces: `TableComponentExtractor::hasTemplatedComponents(array $ast): bool` — true if the `configure()` method's `columns()`/`filters()`/`recordActions()`/`toolbarActions()` call is passed a non-array argument (e.g. `X::get()`), which is the shape produced after customization.
- Produces: `TableAnalyzer::hasTemplatedComponents(): bool` — delegates to the extractor for the loaded AST.
- Consumes: `CustomizationStateChecker` now depends on `ResourceTableLocator`, `TableAstLoader`, and `TableComponentExtractor` (constructor-injected).

- [ ] **Step 1: Write the failing test**

Create `tests/Commands/CustomizeResourceRerunTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

function seedPristineUserResource(): array
{
    $resourceDir = base_path('app/Filament/Resources/Organisation/Users');
    $tablesDir = $resourceDir.'/Tables';
    File::ensureDirectoryExists($tablesDir);

    File::put($resourceDir.'/UserResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users;

class UserResource
{
}
PHP);

    File::put($tablesDir.'/UsersTable.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
PHP);

    return [$resourceDir, $tablesDir];
}

it('refuses to re-customize and preserves generated files (no --force)', function () {
    [, $tablesDir] = seedPristineUserResource();

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);

    $columnBefore = File::get($tablesDir.'/UsersColumn.php');

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(1);

    expect(File::get($tablesDir.'/UsersColumn.php'))->toBe($columnBefore);
});

it('refuses to re-customize even with --force', function () {
    [, $tablesDir] = seedPristineUserResource();

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);
    $columnBefore = File::get($tablesDir.'/UsersColumn.php');

    $this->artisan('filament:customize-resource UserResource --force')->assertExitCode(1);

    expect(File::get($tablesDir.'/UsersColumn.php'))->toBe($columnBefore);
});

it('refuses re-customize when sibling files were deleted but the table is already templated', function () {
    [, $tablesDir] = seedPristineUserResource();

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);

    // Simulate a partial cleanup: sibling generated files gone, table still templated.
    File::delete($tablesDir.'/UsersColumn.php');
    File::delete($tablesDir.'/UsersFilter.php');
    File::delete($tablesDir.'/UsersRecordAction.php');
    File::delete($tablesDir.'/UsersToolbarAction.php');

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Commands/CustomizeResourceRerunTest.php`
Expected: FAIL — re-runs currently succeed (exit 0) and overwrite `UsersColumn.php` with an empty definition.

- [ ] **Step 3: Add templated-form detection to the extractor**

In `src/Services/Table/TableComponentExtractor.php`, add these methods (place after `extractUseStatements()`, before `extractConfigureArguments()`):

```php
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
```

- [ ] **Step 4: Expose detection on the analyzer**

In `src/Services/Table/TableAnalyzer.php`, add after `analyze()`:

```php
    public function hasTemplatedComponents(): bool
    {
        return $this->componentExtractor->hasTemplatedComponents($this->ast);
    }
```

- [ ] **Step 5: Guard the command before generation**

In `src/Commands/CustomizeResourceCommand.php`, insert immediately after the `empty($components)` block (after line 77, before line 79 `$components['resourceNamespace'] = ...`):

```php
        if ($analyzer->hasTemplatedComponents()) {
            $this->error('This resource is already customized — its table delegates to generated component classes.');
            $this->line('Refusing to regenerate (this would overwrite your Column/Filter/Action files). Edit those files directly, or restore the resource to a pristine table to re-run.');

            return self::FAILURE;
        }

```

- [ ] **Step 6: Harden `CustomizationStateChecker` with AST detection**

Replace the whole of `src/Services/Resources/CustomizationStateChecker.php`:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Services\Resources;

use Illuminate\Support\Facades\File;
use Rolland\FilamentResourceCustomizer\Services\Table\TableAstLoader;
use Rolland\FilamentResourceCustomizer\Services\Table\TableComponentExtractor;

class CustomizationStateChecker
{
    public function __construct(
        protected ResourceTableLocator $tableLocator,
        protected TableAstLoader $astLoader,
        protected TableComponentExtractor $componentExtractor
    ) {}

    public function isCustomized(string $resourcePath): bool
    {
        if ($this->hasGeneratedSiblings($resourcePath)) {
            return true;
        }

        $tablePath = $this->tableLocator->findTablePath($resourcePath);

        if ($tablePath) {
            $ast = $this->astLoader->load($tablePath);

            if ($this->componentExtractor->hasTemplatedComponents($ast)) {
                return true;
            }
        }

        return false;
    }

    protected function hasGeneratedSiblings(string $resourcePath): bool
    {
        $resourceDir = dirname($resourcePath);

        $customizedFiles = [
            "{$resourceDir}/Tables/*Column.php",
            "{$resourceDir}/Tables/*Filter.php",
            "{$resourceDir}/Tables/*RecordAction.php",
            "{$resourceDir}/Tables/*ToolbarAction.php",
        ];

        foreach ($customizedFiles as $pattern) {
            if (! empty(File::glob($pattern))) {
                return true;
            }
        }

        return false;
    }
}
```

Verify `ResourceTableLocator::findTablePath()` and `TableAstLoader::load()` signatures match this usage before running (both are used identically in `CustomizeResourceCommand` / `TableAnalyzer`).

- [ ] **Step 7: Run test to verify it passes**

Run: `vendor/bin/pest tests/Commands/CustomizeResourceRerunTest.php`
Expected: PASS (all three cases).

- [ ] **Step 8: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green. In particular the existing `CustomizeResourceCommandTest` first-run tests still pass (pristine tables have array-literal arguments, so `hasTemplatedComponents()` is false).

- [ ] **Step 9: Commit**

```bash
git add src/Services/Table/TableComponentExtractor.php src/Services/Table/TableAnalyzer.php src/Services/Resources/CustomizationStateChecker.php src/Commands/CustomizeResourceCommand.php tests/Commands/CustomizeResourceRerunTest.php
git commit -m "fix: refuse to re-customize a templated resource to prevent data loss"
```

---

### Task 7: Phase 0-A — widen PHP constraint to include 8.5

**Files:**
- Modify: `composer.json:22`

- [ ] **Step 1: Bump the constraint**

In `composer.json`, change the `php` require line:

```json
        "php": "^8.3|^8.4|^8.5",
```

- [ ] **Step 2: Refresh dependencies**

Run: `composer update --no-interaction`
Expected: resolves cleanly, no platform conflict (dev box is PHP 8.5.4).

- [ ] **Step 3: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green.

- [ ] **Step 4: Commit**

```bash
git add composer.json
git commit -m "chore: support PHP 8.5"
```

---

### Task 8: Phase 0-B — document Filament v4/v5 support + prove generated output is valid PHP

**Files:**
- Modify: `README.md` (add a "Requirements" note)
- Test: `tests/Integration/GeneratedOutputParsesTest.php`

**Interfaces:**
- Consumes: `filament:customize-resource` command output files; `PhpParser\ParserFactory` (already a package dependency via `nikic/php-parser`).

**Note on scope:** The package loads no Filament at runtime, so v4/v5 support is a *documentation + verification* concern, not a composer `require`. This task documents the supported range and adds a runnable test asserting every generated file is syntactically valid PHP. Full API-level v4-vs-v5 validation (booting generated resources inside a real Filament app across a CI matrix) is a follow-up that needs CI infrastructure and is out of scope here; note it in the README.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/GeneratedOutputParsesTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;
use PhpParser\ParserFactory;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('generates syntactically valid PHP for every customized file', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Users');
    $tablesDir = $resourceDir.'/Tables';
    File::ensureDirectoryExists($tablesDir);

    File::put($resourceDir.'/UserResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users;

class UserResource
{
}
PHP);

    File::put($tablesDir.'/UsersTable.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
PHP);

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);

    $parser = (new ParserFactory)->createForHostVersion();

    foreach (File::allFiles($tablesDir) as $file) {
        $code = File::get($file->getPathname());
        expect(fn () => $parser->parse($code))->not->toThrow(Throwable::class);
        expect($parser->parse($code))->not->toBeNull();
    }
});
```

- [ ] **Step 2: Run test to verify it passes (guards regressions)**

Run: `vendor/bin/pest tests/Integration/GeneratedOutputParsesTest.php`
Expected: PASS — generated output already parses. (This test is a regression guard; it is expected to pass immediately. If it fails, generation is producing invalid PHP and must be fixed before proceeding.)

- [ ] **Step 3: Document the supported range in the README**

Add a `## Requirements` section near the top of `README.md` (after the intro, before installation):

```markdown
## Requirements

- PHP 8.3, 8.4, or 8.5
- Laravel 11 or 12
- Filament v4 or v5

This package generates and rewrites Filament resource files as source code; it does not load
Filament at runtime, so no `filament/*` package is a hard dependency. Generated output targets
APIs common to Filament v4 and v5. Full application-level compatibility across both majors is
verified manually per release (a CI matrix booting generated resources under each major is planned).
```

- [ ] **Step 4: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add README.md tests/Integration/GeneratedOutputParsesTest.php
git commit -m "docs: document Filament v4/v5 support and add generated-output parse test"
```

---

## Self-Review

**Spec coverage:**
- #1 → Task 6 ✓ (guard + AST state check + tests for force / no-force / deleted-siblings)
- #2 → Task 5 ✓ (panel-nested custom path)
- #3 → Task 5 ✓ (resolver delegates to shared `PermissionTargetResolver`)
- #4 → Task 4 ✓ (ambiguity exception + command handling)
- #5 → Task 2 ✓ (configurable panel path template)
- #6 → Task 3 ✓ (catch + clean failure)
- #7 → Task 1 ✓ (shared `ResourceName::withoutSuffix`)
- Phase 0-A (PHP 8.5) → Task 7 ✓
- Phase 0-B (Filament v4/v5 support) → Task 8 ✓ (documented range + parse test; full CI-matrix validation explicitly deferred with rationale)
- Shared refactors (name normalizer, placement resolver) → Tasks 1 and 5 ✓

**Placeholder scan:** No TBD/TODO/"add error handling"/"similar to". Every code step shows complete code.

**Type consistency:** `ResourceName::withoutSuffix` used identically in Tasks 1 and 5. `PermissionTargetResolver::resolve` signature unchanged (Task 5 changes behavior, not signature) so its existing consumer `PermissionClassRenderer` needs no edit. `hasTemplatedComponents` named identically on both `TableComponentExtractor` (array-arg) and `TableAnalyzer` (no-arg delegate). `AmbiguousResourceException::$matches` (list<string>) consumed as `$e->matches` in both commands.

**Deviations from spec noted:**
- #6: committed fix is catch-and-clean-fail (spec's stated acceptable alternative), not auto-creating the `manage` structure — auto-creation is a larger change deferred to Phase 2.
- #1: uses a dedicated `hasTemplatedComponents()` detector rather than changing `extractArrayElements()`'s return type, to avoid altering the downstream extraction contract; same protection, smaller blast radius.

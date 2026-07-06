# Shield-Aware Permission Helper Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `BaseResourcePermissions::can()/permissions()/permissionKey()` produce gate strings that match Filament Shield's real registered permissions, by delegating to Shield's own public key-builder behind a testable seam.

**Architecture:** A `PermissionGateResolver` interface isolates the Shield coupling. `BaseResourcePermissions` (consumer) resolves the interface from the container and delegates all gate-string construction to it. `ShieldGateResolver` (provider) implements the interface by calling Shield's public `FilamentShield::defaultPermissionKeyBuilder()` + `Utils::prefixPermissionWithPanel()`, throwing a clear exception when Shield is absent.

**Tech Stack:** PHP 8.3–8.5, Laravel 11/12, Pest 4, PHPStan level 5, orchestra/testbench 10, bezhansalleh/filament-shield (dev, require-dev only).

## Global Constraints

- TDD: write the failing test, run it red, implement, run it green, commit.
- Existing suite must stay green after every task: `composer test`.
- PHPStan level 5 must stay clean after every task: `composer analyse`.
- No `filament/*` or `bezhansalleh/filament-shield` in composer `require` — Shield is `require-dev` only; the customize/table features stay Shield-free.
- The three helper methods (`can`, `permissions`, `permissionKey`) require Shield at runtime; without it they throw `RuntimeException` — never return a silent `false`/`[]`.
- Delegate gate-string construction to Shield's public API; do not reimplement Shield's format.
- Conventional Commits. `docs/` is gitignored — do not commit plan/spec files.
- This work lands on an integration branch `phase-2` (not `main`); no release is cut mid-plan.

---

### Task 1: `PermissionGateResolver` interface + `BaseResourcePermissions` rewrite (consumer side)

**Files:**
- Create: `src/Contracts/PermissionGateResolver.php`
- Modify: `src/Support/BaseResourcePermissions.php` (full rewrite)
- Test: `tests/Support/BaseResourcePermissionsTest.php`

**Interfaces:**
- Produces: `Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver::key(string $method, string $resource): string`
- Produces: `BaseResourcePermissions::permissionKey(string $method): string`, `::permissions(): array` (list<string> of full gate keys), `::can(string $method): bool`
- Consumes: nothing from other tasks. In this task the resolver is supplied by a test-bound fake; the production binding to `ShieldGateResolver` arrives in Task 2. Committed state after Task 1: calling the helper in a real app throws "unresolvable" until Task 2 binds the default — acceptable on the `phase-2` branch, and the tests bind a fake so they pass.

- [ ] **Step 1: Write the failing test**

Create `tests/Support/BaseResourcePermissionsTest.php`:

```php
<?php

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;
use Rolland\FilamentResourceCustomizer\Support\BaseResourcePermissions;

class TestRequestPermissions extends BaseResourcePermissions
{
    public static function getResourceName(): string
    {
        return 'Request';
    }

    public static function methods(): array
    {
        return ['viewPdf', 'submitRequest'];
    }
}

class GateTestUser extends AuthUser {}

function fakeResolver(): PermissionGateResolver
{
    return new class implements PermissionGateResolver
    {
        public function key(string $method, string $resource): string
        {
            return "gate::{$method}::{$resource}";
        }
    };
}

beforeEach(function () {
    app()->instance(PermissionGateResolver::class, fakeResolver());
});

it('builds a permission key by delegating to the resolver', function () {
    expect(TestRequestPermissions::permissionKey('viewPdf'))->toBe('gate::viewPdf::Request');
});

it('maps every method through the resolver for permissions()', function () {
    expect(TestRequestPermissions::permissions())->toBe([
        'gate::viewPdf::Request',
        'gate::submitRequest::Request',
    ]);
});

it('returns true from can() when the current user holds the gate', function () {
    Gate::define('gate::viewPdf::Request', fn ($user) => true);
    $this->actingAs(new GateTestUser);

    expect(TestRequestPermissions::can('viewPdf'))->toBeTrue();
});

it('returns false from can() when the gate is not granted', function () {
    Gate::define('gate::viewPdf::Request', fn ($user) => false);
    $this->actingAs(new GateTestUser);

    expect(TestRequestPermissions::can('viewPdf'))->toBeFalse();
});

it('returns false from can() when there is no authenticated user', function () {
    expect(TestRequestPermissions::can('viewPdf'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Support/BaseResourcePermissionsTest.php`
Expected: FAIL — `PermissionGateResolver` interface not found / `permissionKey` undefined.

- [ ] **Step 3: Create the interface**

Create `src/Contracts/PermissionGateResolver.php`:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Contracts;

interface PermissionGateResolver
{
    /**
     * Build the full authorization gate string for a permission method on a resource,
     * including any panel prefix and case transformation.
     */
    public function key(string $method, string $resource): string;
}
```

- [ ] **Step 4: Rewrite `BaseResourcePermissions`**

Replace the whole of `src/Support/BaseResourcePermissions.php`:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Support;

use Illuminate\Support\Facades\Auth;
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;

abstract class BaseResourcePermissions
{
    abstract public static function getResourceName(): string;

    /**
     * @return array<int, string>
     */
    public static function methods(): array
    {
        return [];
    }

    /**
     * Full authorization gate string for a single method, e.g. "system:ViewPdf:Request".
     */
    public static function permissionKey(string $method): string
    {
        return app(PermissionGateResolver::class)->key($method, static::getResourceName());
    }

    /**
     * Full gate strings for every method in methods().
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_map(
            static fn (string $method): string => static::permissionKey($method),
            static::methods()
        );
    }

    /**
     * Whether the current user holds the gate for the given method.
     */
    public static function can(string $method): bool
    {
        return Auth::user()?->can(static::permissionKey($method)) ?? false;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Support/BaseResourcePermissionsTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green, PHPStan clean.

- [ ] **Step 7: Commit**

```bash
git add src/Contracts/PermissionGateResolver.php src/Support/BaseResourcePermissions.php tests/Support/BaseResourcePermissionsTest.php
git commit -m "feat: delegate BaseResourcePermissions gate strings through a resolver"
```

---

### Task 2: `ShieldGateResolver` + Shield dependency + binding (provider side)

**Files:**
- Modify: `composer.json` (add `bezhansalleh/filament-shield` to `require-dev`)
- Modify: `tests/TestCase.php` (register Shield's provider)
- Create: `src/Shield/ShieldGateResolver.php`
- Modify: `src/FilamentResourceCustomizerServiceProvider.php:28-33` (bind the interface)
- Test: `tests/Shield/ShieldGateResolverTest.php`

**Interfaces:**
- Consumes: `PermissionGateResolver` (Task 1).
- Produces: `Rolland\FilamentResourceCustomizer\Shield\ShieldGateResolver implements PermissionGateResolver` with an overridable `protected function shieldIsAvailable(): bool`.
- Produces: container binding `PermissionGateResolver::class → ShieldGateResolver::class`.

- [ ] **Step 1: Add Shield to require-dev and install**

Add to `composer.json` `require-dev` (keep `sort-packages` order — place after `larastan/larastan`):

```json
        "bezhansalleh/filament-shield": "*",
```

Run: `composer update bezhansalleh/filament-shield --with-all-dependencies --no-interaction`

Expected: Shield + its Filament/spatie-permission dependencies install. If resolution fails against the current PHP/Laravel, report BLOCKED with the composer error — do not pin blindly.

- [ ] **Step 2: Verify Shield is usable in testbench (spike — no commit)**

Run this one-off check to confirm Shield's facade + config resolve under testbench before writing the real test:

```bash
vendor/bin/pest --filter="shield spike" tests/Shield/ShieldGateResolverTest.php || true
```

First create a temporary spike test in `tests/Shield/ShieldGateResolverTest.php`:

```php
<?php

use BezhanSalleh\FilamentShield\Facades\FilamentShield;

it('shield spike: defaultPermissionKeyBuilder is callable', function () {
    expect(FilamentShield::defaultPermissionKeyBuilder(
        affix: 'viewPdf', separator: ':', subject: 'Request', case: 'pascal'
    ))->toBe('ViewPdf:Request');
})->group('spike');
```

If this errors because Shield's service provider is not registered, add it in Step 3 first, then re-run. If it still cannot boot under testbench (e.g. requires a live Filament panel), STOP and report BLOCKED — the integration assertion strategy needs revisiting (fallback: instantiate `new FilamentShield` directly and assert `defaultPermissionKeyBuilder`, keeping the resolver's facade path covered only by the fake in Task 1). Delete the spike test once the real tests below replace it.

- [ ] **Step 3: Register Shield's provider in the test harness**

In `tests/TestCase.php`, replace `getPackageProviders()`:

```php
    protected function getPackageProviders($app)
    {
        $providers = [
            FilamentResourceCustomizerServiceProvider::class,
        ];

        if (class_exists(\BezhanSalleh\FilamentShield\FilamentShieldServiceProvider::class)) {
            $providers[] = \BezhanSalleh\FilamentShield\FilamentShieldServiceProvider::class;
        }

        return $providers;
    }
```

- [ ] **Step 4: Write the failing tests**

Replace `tests/Shield/ShieldGateResolverTest.php` (remove the spike test) with:

```php
<?php

use Rolland\FilamentResourceCustomizer\Shield\ShieldGateResolver;

it('builds a shield gate string from configured case and separator', function () {
    config([
        'filament-shield.permissions.case' => 'pascal',
        'filament-shield.permissions.separator' => ':',
    ]);

    // No Filament panel is current under testbench, so prefixPermissionWithPanel
    // adds no prefix; this asserts the case + separator delegation to Shield.
    expect((new ShieldGateResolver)->key('viewApprovalTrail', 'Request'))
        ->toBe('ViewApprovalTrail:Request');
});

it('throws a clear exception when shield is unavailable', function () {
    $resolver = new class extends ShieldGateResolver
    {
        protected function shieldIsAvailable(): bool
        {
            return false;
        }
    };

    expect(fn () => $resolver->key('viewPdf', 'Request'))
        ->toThrow(RuntimeException::class, 'filament-shield is required');
});

it('is the bound default resolver', function () {
    expect(app(\Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver::class))
        ->toBeInstanceOf(ShieldGateResolver::class);
});
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Shield/ShieldGateResolverTest.php`
Expected: FAIL — `ShieldGateResolver` not found / not bound.

- [ ] **Step 6: Create `ShieldGateResolver`**

Create `src/Shield/ShieldGateResolver.php`:

```php
<?php

namespace Rolland\FilamentResourceCustomizer\Shield;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;
use RuntimeException;

class ShieldGateResolver implements PermissionGateResolver
{
    public function key(string $method, string $resource): string
    {
        if (! $this->shieldIsAvailable()) {
            throw new RuntimeException(
                'filament-shield is required for permission gate checks. '
                .'Install bezhansalleh/filament-shield, or avoid calling '
                .'BaseResourcePermissions::can()/permissions()/permissionKey().'
            );
        }

        $permissions = Utils::getConfig()->permissions;

        $base = FilamentShield::defaultPermissionKeyBuilder(
            affix: $method,
            separator: $permissions->separator,
            subject: $resource,
            case: $permissions->case,
        );

        return Utils::prefixPermissionWithPanel($base);
    }

    protected function shieldIsAvailable(): bool
    {
        return class_exists(FilamentShield::class) && class_exists(Utils::class);
    }
}
```

- [ ] **Step 7: Bind the interface in the service provider**

In `src/FilamentResourceCustomizerServiceProvider.php`, add the imports after the existing command imports:

```php
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;
use Rolland\FilamentResourceCustomizer\Shield\ShieldGateResolver;
```

Then in `packageRegistered()`, add the binding after the existing singleton line:

```php
    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->app->singleton('filament-resource-customizer', fn () => new FilamentResourceCustomizer);
        $this->app->bind(PermissionGateResolver::class, ShieldGateResolver::class);
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Shield/ShieldGateResolverTest.php`
Expected: PASS (3 tests). If the first test fails because `Utils::getConfig()` returns a different default `case`/`separator`, adjust the `config([...])` set values in the test to match the keys Shield actually reads (confirm the key path with the spike) — do not change the asserted format.

- [ ] **Step 9: Run full suite + analyse**

Run: `composer test && composer analyse`
Expected: all green. PHPStan now sees Shield classes (installed via require-dev), so references in `ShieldGateResolver` resolve. Task 1's `BaseResourcePermissionsTest` still passes (its fake binding overrides the default via `app()->instance`).

- [ ] **Step 10: Commit**

```bash
git add composer.json tests/TestCase.php src/Shield/ShieldGateResolver.php src/FilamentResourceCustomizerServiceProvider.php tests/Shield/ShieldGateResolverTest.php
git commit -m "feat: add ShieldGateResolver delegating to filament-shield"
```

---

### Task 3: Documentation — requirements + migration

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Document the Shield requirement and the helper API**

In `README.md`, under the existing `## Requirements` section, append:

```markdown

### Permission helper (`BaseResourcePermissions`)

`BaseResourcePermissions::can()`, `permissions()`, and `permissionKey()` build authorization
gate strings by delegating to Filament Shield, so they require
[`bezhansalleh/filament-shield`](https://github.com/bezhanSalleh/filament-shield) to be installed.
Called without it, they throw a `RuntimeException`. The rest of the package (table customization,
file generation) does not require Shield.

```php
// Full gate string, matching what Shield registered (incl. panel prefix + case):
RequestPermissions::permissionKey('viewApprovalTrail'); // "system:ViewApprovalTrail:Request"

// Authorization check for the current user:
RequestPermissions::can('viewApprovalTrail');           // bool

// All gate strings for the class's methods():
RequestPermissions::permissions();                      // ["system:ViewApprovalTrail:Request", ...]
```

A custom `FilamentShield::buildPermissionKeyUsing()` closure is not honored by these helpers; they
use Shield's default key builder.
```

- [ ] **Step 2: Add an upgrade note**

In `README.md`, add (or append to) an `## Upgrading` section near the bottom:

```markdown
## Upgrading

### To the shield-aware permission helper

`BaseResourcePermissions::permissions()` now returns full Shield gate strings (e.g.
`system:ViewApprovalTrail:Request`) instead of the previous `method:Resource` form, and `can()`
now returns correct results in panel-prefixed apps. If you hardcoded gate strings in your policies,
you can replace them with `YourPermissions::can('method')`. These methods now require
`bezhansalleh/filament-shield`.
```

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document shield-aware permission helper and upgrade notes"
```

---

## Self-Review

**Spec coverage:**
- Architecture (interface + ShieldGateResolver + base rewrite + binding) → Tasks 1 & 2 ✓
- API surface (`permissionKey`/`permissions`/`can`) → Task 1 ✓
- Delegate to Shield's public API (`defaultPermissionKeyBuilder` + `prefixPermissionWithPanel`, config via `Utils::getConfig()->permissions`) → Task 2 ✓
- Throw on Shield absent + overridable `shieldIsAvailable()` → Task 2 ✓
- Shield in require-dev only, not in `require` → Task 2 ✓ / Global Constraints ✓
- Testing: unit via fake resolver (no Shield) + throw-path via override + real integration → Tasks 1 & 2 ✓
- Docs / migration / Shield-requirement note → Task 3 ✓
- Out of scope (generator/stubs/commands untouched) → honored; no task edits them ✓

**Placeholder scan:** No TBD/TODO/"add error handling"/"similar to". The Task 2 spike step is a real, bounded verification with explicit BLOCKED criteria, not a placeholder.

**Type consistency:** `PermissionGateResolver::key(string $method, string $resource): string` is defined in Task 1 and implemented with the identical signature in Task 2. `permissionKey`/`permissions`/`can` names match across the base class, its test, and the README examples. The fake resolver in Task 1 and `ShieldGateResolver` in Task 2 implement the same interface method.

**Known risk (flagged, not a placeholder):** Task 2 depends on Shield booting under testbench. Step 2 is a spike with an explicit BLOCKED escalation and a named fallback (instantiate `FilamentShield` directly) so the implementer does not fake a passing test if the harness cannot boot Shield.

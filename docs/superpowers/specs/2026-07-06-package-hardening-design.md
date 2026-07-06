# Package Hardening — Phase 0 (Constraints) + Phase 1 (Bug Fixes)

**Date:** 2026-07-06
**Package:** rolland97/filament-resource-customizer
**Status:** Approved design, pending implementation plan

## Context

Audit of the package (src/, ~2000 LOC) plus its real-world consumer at `/home/rolland/projects/ewp`
(installed v1.1.0, 5 Filament panels, 30 generated `*Permissions.php`) surfaced three workstreams:

1. Version/constraint gaps
2. Seven correctness bugs
3. Capability gaps (empty stub, dead runtime helper, no panel scoping)

This spec covers **Phase 0 (constraints)** and **Phase 1 (all 7 bugs)**.
**Phase 2 (capability redesign)** is explicitly deferred to its own brainstorm — it is a breaking
redesign (shield-aware `can()`, panel-scoped/inheritable permission classes, richer generation)
that deserves dedicated design.

### Key discovery shaping the design

The package has **no `filament/*` in `require`** and installs **no Filament for its tests**.
It is a pure text/AST code generator: it parses consumer resource files via `nikic/php-parser`
and emits Filament code as string templates. The `Filament\...` references in `src/`/`stubs/`
are string literals in generated output, not real imports. Current tests only string-match
generated output — nothing proves the output compiles under any real Filament version.

## Phase 0 — Constraints

### A. PHP version
- `require.php`: `^8.3|^8.4` → `^8.3|^8.4|^8.5`
- Verified safe: full test suite is green on PHP 8.5.4 (current dev box; ewp also runs 8.5.4).

### B. Filament version support (v4 + v5)
Decision: **do NOT add `filament/filament` to `require`.** The package does not load Filament at
runtime, and consumers already have it. Instead deliver "v4 + v5 support" as verifiable compatibility:

- Declare supported Filament range (`^4.0 | ^5.0`) in README + `keywords` — documentation, not a composer require.
- Add a `require-dev` Filament dependency + integration test that generates files against a real
  Filament install and asserts they **parse/boot** (not just string-match). Only one Filament major
  is installable at a time, so v4/v5 coverage is a **CI matrix** (swap the Filament constraint per lane).
- Investigate real v4→v5 API divergence that affects generation/parsing (`Table::configure` shape,
  `columns()`/`filters()`/`recordActions()`/`toolbarActions()` method names, action class names,
  `FiltersLayout`, column class namespaces). Adjust AST extractor + stubs to handle both. This
  investigation is a task within Phase 1's test work.

## Phase 1 — Bug Fixes

All fixes are test-first (write the failing test, then fix). Ordered isolated → complex.

### #1 — 🔴 CRITICAL: Re-run silently wipes column/filter/action definitions
- **Files:** `src/Services/Table/TableComponentExtractor.php:125`, `src/Commands/CustomizeResourceCommand.php:73`, `src/Services/Resources/CustomizationStateChecker.php`
- **Root cause:** `extractArrayElements()` returns `[]` for BOTH a genuine empty literal (`->filters([])`)
  and a templated call (`->columns(UsersColumn::get())`, a `StaticCall`, not `Array_`). After first
  customization the table file holds the templated form; re-running extracts empty arrays and overwrites
  the populated Column/Filter/Action files with `[]`, printing success (exit 0). Triggers via `--force`
  OR when sibling files are missing/deleted (then `isCustomized()` returns false, no `--force` needed).
- **Fix:**
  - `extractArrayElements()` returns `null` (not `[]`) when the argument is not an `Array_` node.
  - Analyzer propagates: any section resolving to `null` ⇒ resource is already in customized (templated) form.
  - `CustomizeResourceCommand`: templated form detected ⇒ **hard error, refuse to regenerate, even with `--force`.**
    `--force` retains its role: overwrite sibling files when re-running on a *pristine* resource only.
  - `CustomizationStateChecker::isCustomized()`: inspect the table file AST (does a `columns()`/`filters()`/
    action call target `X::get()`?) instead of only globbing sibling files — cannot be defeated by deleted siblings.
- **Rationale for "always refuse":** once customized, the generated Column/Filter/Action files ARE the
  source of truth; the intended workflow is to hand-edit those, never regenerate. Re-deriving from siblings
  was rejected — stale/partial siblings would produce corrupt output.
- **Test:** customize a pristine resource → customize again → assert 2nd invocation fails AND the generated
  files are byte-for-byte untouched.

### #2 — 🟠 HIGH: `placement=custom` cross-panel filename/namespace collision
- **File:** `src/Support/PermissionTargetResolver.php:24`
- **Root cause:** when `placement=custom`, output path is panel-agnostic
  (`{custom_path}/{ResourceName}Permissions.php`). Two panels with the same short resource name
  (`Admin\...\UserResource`, `Customer\...\UserResource`) resolve to the same file + namespace →
  second run overwrites the first → silent authorization corruption.
- **Fix:** thread the resolved panel into `PermissionTargetResolver`; when `placement=custom`, nest output
  under a panel segment (`{custom_path}/{Panel}/{ResourceName}Permissions.php`) and include the panel in
  the namespace.
- **Test:** two panels, same resource name, `placement=custom` → distinct output paths + namespaces.

### #3 — 🟠 HIGH: Shield-config resolver ignores `custom_path`
- **File:** `src/Support/PermissionsClassResolver.php:12`
- **Root cause:** `resolveForResourceFile()` only checks the resource's own dir + immediate parent for
  `{Base}Permissions.php`; it has no knowledge of `placement`/`custom_path`. With `placement=custom`,
  `shield-config` never finds the generated classes and silently falls back to default methods for every resource.
- **Fix:** extract a **shared placement-resolution helper** consumed by BOTH `PermissionTargetResolver`
  (write path) and `PermissionsClassResolver` (read path) → single source of truth for where permission
  classes live.
- **Test:** `placement=custom` + generated class in `custom_path` → `shield-config` resolves it (not defaults).

### #4 — 🟠 HIGH: Ambiguous cross-panel resource name silently picks first
- **File:** `src/Support/ResourceLocator.php:16`
- **Root cause:** with `--panel` omitted, `findResourcePath()` returns the first glob match across all
  merged panel dirs (alphabetical) with no warning when the same name exists in multiple panels.
- **Fix:** collect ALL matches; if more than one across panels, error out listing the matches and require `--panel`.
- **Test:** duplicate resource name across two panels, no `--panel` → failure listing both panels.

### #5 — 🟡 MEDIUM: `--panel` path hardcoded, ignores `resources_path`
- **File:** `src/FilamentResourceCustomizer.php:44`
- **Root cause:** `resourcesPathsForPanel()` hardcodes `app/Filament/{panel}/Resources`; a project with a
  custom `resources_path` gets "Panel not found" for every `--panel` invocation across all four commands.
- **Fix:** derive the per-panel path from the configured `resources_path` template.
- **Test:** custom `resources_path` + `--panel=X` resolves correctly.

### #6 — 🟡 MEDIUM: Shield config missing `manage` key crashes
- **File:** `src/Support/ShieldConfigUpdater.php:49`
- **Root cause:** when the target `filament-shield.php` has no `resources.manage` array, the code builds a
  synthetic `Array_` AST node with no `startFilePos`/`endFilePos`, then throws
  `RuntimeException('Unable to determine array position for formatting.')`. `ShieldConfigCommand` does not
  catch it → raw stack trace instead of a clean error.
- **Fix:** when `manage` is absent, build the structure as a raw-text insert rather than AST-splicing a
  synthetic node; AND wrap the call in `ShieldConfigCommand` to catch `RuntimeException` → `error()` + `FAILURE`.
- **Test:** shield config without a `manage` key → command succeeds (or fails cleanly), no unhandled exception.

### #7 — 🟡 MEDIUM: Resource-name derivation strips every "Resource"
- **File:** `src/Services/Context/ResourceContext.php:34`
- **Root cause:** `str_replace('Resource', '', $className)` is a global replace. `HumanResourceResource` →
  `Human` (should be `HumanResource`); `ResourceResource` → `''`. Corrupts generated names/namespaces.
- **Fix:** suffix-strip via `Str::endsWith` — reuse/extract the correct logic already in
  `ResourceLocator::normalizeResourceName` as a shared normalizer.
- **Test:** `HumanResourceResource` → `HumanResource`; `ResourceResource` → `Resource`.

## Shared refactors (serve the fixes, not speculative)

- **Placement-resolution helper** — single source of truth for permission-class location, consumed by
  `PermissionTargetResolver` (#2) and `PermissionsClassResolver` (#3).
- **Resource-name normalizer** — suffix-aware, consumed by `ResourceContext` (#7) and existing `ResourceLocator`.

## Implementation order

1. #7, #5, #6 — isolated, quick
2. #4 — ambiguity guard
3. #3 + #2 — share the placement helper
4. #1 — largest, AST work
5. Phase 0-B integration test harness + v4/v5 divergence investigation
6. Phase 0-A php constraint bump

## Testing

- Every fix: failing test first, then fix (TDD).
- Existing suite (12 tests) must stay green.
- New integration test proves generated output parses/boots under a real Filament install (CI matrix v4/v5).
- Run `composer test` + `composer analyse` (phpstan level 5) after each fix; both must pass.

## Out of scope (deferred to Phase 2 brainstorm)

- Shield-aware `can()`/`permissions()` honoring `case`/`separator`/`panel_prefix`.
- Panel-scoped / inheritable permission classes (ewp's System→subclass pattern).
- Richer stub generation (consts, populated `methods()`, matching policy stubs).
- Round-tripping `single_parameter_methods` and base/abstract-resource shield rows.

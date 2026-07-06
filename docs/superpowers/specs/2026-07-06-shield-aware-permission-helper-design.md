# Shield-Aware Permission Helper — Design (Phase 2, Sub-project A)

**Date:** 2026-07-06
**Package:** rolland97/filament-resource-customizer
**Status:** Approved design, pending implementation plan
**Phase:** 2 of the package roadmap; sub-project **A** of four (A → B → C → D)

## Context

Phase 2 is a capability redesign decomposed into four ordered, independently-shipped
sub-projects:

- **A. Shield-aware runtime helper** — this spec.
- **B. Panel-scoped / inheritable permission classes** — deferred, own spec.
- **C. Richer stub generation** (consts, populated `methods()`, policy stubs) — deferred.
- **D. Round-trip shield config extras** (`single_parameter_methods`, base-resource rows) — deferred.

### The problem A solves

`BaseResourcePermissions::can()` / `permissions()` build gate strings as
`"{method}:{ResourceName}"` — camelCase method, no panel prefix. Real Filament Shield gates
in a panel-prefixed app look like `system:ViewApprovalTrail:Request`
(`{panel-id}{sep}{PascalMethod}{sep}{Resource}`). The helper's output never matches, so it is
effectively dead: the ewp consumer abandoned it and hardcoded gate strings across five policy
trees.

A makes the helper produce gate strings that match what Shield actually registered, by
**delegating to Shield's own public key-builder** rather than reimplementing Shield's format
(reimplementation is exactly the drift that broke it originally).

### Grounding facts (verified in ewp's vendored filament-shield, `dev-main`)

- `BezhanSalleh\FilamentShield\Facades\FilamentShield::defaultPermissionKeyBuilder(string $affix, string $separator, string $subject, string $case): string` — **public**; returns `{format(case,affix)}{sep}{format(case,subject)}`, e.g. `ViewApprovalTrail:Request`.
- `BezhanSalleh\FilamentShield\Support\Utils::prefixPermissionWithPanel(string $permission): string` — **public static**; prepends the current panel id + panel-prefix separator when panel-prefixing is enabled (internally checks `isPanelPrefixEnabled()`, `getCurrentPanelId()`, `getPanelPrefixSeparator()`).
- `Utils::getConfig()->permissions` exposes `->case` and `->separator`.
- Panel prefixing intentionally uses the **current** panel — this matches how Shield's own gate checks resolve, so delegating keeps our keys consistent with Shield's without the class having to self-declare its panel.
- Known gap: a custom `FilamentShield::buildPermissionKeyUsing(Closure)` is bypassed by `defaultPermissionKeyBuilder`; there is no public closure-aware single-key builder. The helper will not honor that closure (documented limitation).

## Decisions (locked)

1. **Delegate to Shield's public API** for gate-string construction (not reimplement, not hybrid).
2. **Throw a clear exception** when the helper is used without filament-shield installed (no silent `false`).
3. **Isolate the Shield coupling behind a seam** (`PermissionGateResolver` interface) so the base
   class is testable without Shield and all version-fragile calls live in one file.
4. `filament-shield` stays **out of `require`**; added to `require-dev` for the integration lane.

## Architecture

```
Contracts/PermissionGateResolver          (interface)
    key(string $method, string $resource): string   // full gate string incl. panel prefix

Shield/ShieldGateResolver                 (implements PermissionGateResolver)
    key():
        ensureShieldAvailable();          // throws RuntimeException if Shield absent
        $cfg = Utils::getConfig()->permissions;      // ->case, ->separator
        $base = FilamentShield::defaultPermissionKeyBuilder(
            affix: $method, separator: $cfg->separator, subject: $resource, case: $cfg->case);
        return Utils::prefixPermissionWithPanel($base);

BaseResourcePermissions                   (rewritten)
    resolver() => app(PermissionGateResolver::class)
```

`FilamentResourceCustomizerServiceProvider` binds
`PermissionGateResolver::class → ShieldGateResolver::class` (default). All Shield-version-fragile
code lives only in `ShieldGateResolver`.

## API Surface — `BaseResourcePermissions`

```php
abstract class BaseResourcePermissions
{
    abstract public static function getResourceName(): string;

    public static function methods(): array;      // unchanged; subclasses override

    // Full gate string for one method, incl. panel prefix + case transform.
    // permissionKey('viewApprovalTrail') => 'system:ViewApprovalTrail:Request'
    public static function permissionKey(string $method): string;

    // Full gate string for every entry in methods().  array<int, string>
    public static function permissions(): array;

    // Current user holds the gate for $method?
    // Auth::user()?->can(static::permissionKey($method)) ?? false
    public static function can(string $method): bool;
}
```

- `permissionKey()` is new + public — policies/UI reference the exact gate without hardcoding format.
- `can()` takes the raw method name (`'viewPdf'` or a const value); the resolver applies case + panel.
- The resolver is fetched via `app(PermissionGateResolver::class)` **per call** — panel prefix is
  request-scoped, so no static caching of the resolved instance.

## Shield-Absent Behavior + Config

`ShieldGateResolver::ensureShieldAvailable()` (called at the top of `key()`):

```php
if (! class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)
    || ! class_exists(\BezhanSalleh\FilamentShield\Support\Utils::class)) {
    throw new \RuntimeException(
        'filament-shield is required for permission gate checks. Install '
        .'bezhansalleh/filament-shield, or avoid calling '
        .'BaseResourcePermissions::can()/permissions()/permissionKey().'
    );
}
```

The `class_exists` check is extracted into an overridable protected method
(`shieldIsAvailable(): bool`) so a test double can force "absent" and assert the throw without
uninstalling Shield.

Config: `case` / `separator` read from `Utils::getConfig()->permissions`. Panel prefix fully
delegated to `Utils::prefixPermissionWithPanel()` (no panel logic in our code).

Composer: `filament-shield` NOT in `require`; added to `require-dev`. README documents that
`can()` / `permissions()` / `permissionKey()` require filament-shield.

## Testing

**Unit (no Shield installed) — via the seam:**
- Bind a fake `PermissionGateResolver` returning a deterministic key (e.g. `"{$method}|{$resource}"`).
- Assert: `permissionKey('viewPdf')` delegates with the right method + `getResourceName()`;
  `permissions()` maps every `methods()` entry through the resolver; `can('x')` is true when
  `Auth::user()->can(key)` is true (define the gate via `Gate::define`) and false when no user.
- Assert the throw path: subclass `ShieldGateResolver` overriding `shieldIsAvailable()` to return
  `false`, then expect `RuntimeException` from `key()`.

**Integration (Shield installed, require-dev):**
- Configure `filament-shield.permissions` (`case=pascal`, `separator=:`, `panel_prefix=true`) and
  assert `ShieldGateResolver::key('viewApprovalTrail', 'Request')` equals the expected
  `{panel}:ViewApprovalTrail:Request` for the active panel.

**CI:** `filament-shield` in require-dev installs with Filament. This ties into the deferred
Filament v4/v5 matrix; the integration lane runs per Filament major using whatever Shield version
resolves against that major, and logs any skipped combination rather than silently dropping it.

Global constraints (carried from the package): TDD; existing suite stays green; PHPStan level 5
clean; no `filament/*` or `filament-shield` in `require`; Conventional Commits; `docs/` gitignored
(not committed).

## Backward Compatibility + Versioning

- **Breaking:** `permissions()` return meaning changes — old camelCase `"{method}:{Resource}"`
  (non-functional; zero real consumers per audit) → full Shield gates.
- `can()` signature unchanged but now returns correct results (previously effectively always false
  in panel-prefixed apps).
- `permissionKey()` is net-new.
- **Soft new dependency:** the three methods now require Shield installed.
- **Semver:** ships in the Phase-2 **v2.0.0** major. A is self-contained and could be released
  ahead of B/C/D; land it on a `phase-2` integration branch and decide the release cut at milestone
  time, not now.
- **Migration note (README/UPGRADE):** apps that hardcoded gate strings (ewp) can migrate to
  `Permissions::can('method')`; requires filament-shield; custom `buildPermissionKeyUsing` closures
  not honored.

## Out of Scope (later Phase-2 sub-specs)

- B: panel-scoped / inheritable permission classes (generator awareness of base→subclass).
- C: richer stub generation — consts, populated `methods()`, matching policy stubs.
- D: round-trip shield config extras — `single_parameter_methods`, base/abstract-resource rows.

A changes only the runtime base class + the new resolver/interface + service-provider binding. It
does NOT touch the generator, stubs, or commands.

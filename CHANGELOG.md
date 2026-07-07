# Changelog

All notable changes to `filament-resource-customizer` will be documented in this file.

## v2.0.0 - 2026-07-07

Phase 2: the shield-aware permission layer — permission helpers that produce real Filament Shield gate strings, a configurable and populated permission-class generator, and verified shield-config round-tripping.

### Changed (breaking)

- `BaseResourcePermissions::can()`, `permissions()`, and `permissionKey()` now build gate strings by delegating to Filament Shield's own key builder, so they match the permissions Shield actually registered (panel prefix + case) instead of the old non-functional `"{method}:{Resource}"` form. `permissions()`'s return value changes accordingly, and these three methods now require `bezhansalleh/filament-shield` to be installed (they throw a clear `RuntimeException` otherwise). Apps that hardcoded gate strings can migrate to `YourPermissions::can('method')`. See the Upgrading section in the README.
- Generated permission classes are now populated from `shield.default_methods` (SCREAMING_SNAKE consts + a `methods()` returning them) instead of an empty stub. Set `shield.default_methods` to `[]` for the previous empty shape. Only affects newly generated / regenerated files.

### Added

- `permissions.base_class` config — generated permission classes can extend a shared or custom base class instead of the built-in `BaseResourcePermissions` (imported automatically unless it lives in the generated namespace).
- `RequestPermissions::permissionKey('method')` — the full gate string for a permission, matching Shield, without hardcoding the format.
- Documentation of how `filament:shield-config` preserves hand-maintained config: everything outside `resources.manage` is left untouched; `--merge` / `shield.merge` keeps hand-added rows; `shield.static_resources` injects rows on every run.

### Internal

- Shield coupling isolated behind a `PermissionGateResolver` seam (`ShieldGateResolver`), keeping `filament-shield` a dev-only dependency and the helpers testable without it. Panel prefixing is applied only on Shield versions that expose it.

## v1.2.0 - 2026-07-06

Hardening release: correctness fixes, PHP 8.5, documented Filament v4/v5 support.

### Fixed

- **Critical:** `filament:customize-resource` no longer silently overwrites generated Column/Filter/Action files when re-run on an already-customized resource — it now refuses (even with `--force`) and detects the templated state via AST.
- Custom permission placement (`placement=custom`) is now panel-scoped, preventing same-named resources in different panels from overwriting each other's permission class.
- `filament:shield-config` now finds custom-placement permission classes instead of falling back to default methods.
- Ambiguous cross-panel resource names now error and ask for `--panel` instead of silently customizing the alphabetically-first match.
- `filament:shield-config` fails cleanly (instead of an uncaught exception) when the target config lacks a `resources.manage` array.
- Resource-name derivation strips only the trailing `Resource` suffix (e.g. `HumanResourceResource` no longer mangled).

### Added

- PHP 8.5 support.
- Configurable per-panel resource path via `panels.path_template`.
- Documented Filament v4 and v5 support; integration test asserting generated output is valid PHP.

## v1.1.0 - 2026-01-27

Multi-panel support

- Auto-detect resources in `app/Filament/*/Resources`.
- Allow `resources_path` to be a string or array.
- Add `--panel` to target a single panel for commands.
- Shield config now aggregates multiple resource roots.
- New tests for auto-detect and `--panel`.

## v1.0.1 - 2026-01-22

- drop unused database stubs to keep package lean
- update phpstan paths to match removed folder

## v1.0.0 - 2026-01-22

Initial stable release of Filament Resource Customizer.

Highlights

- Split Filament resource tables into dedicated column, filter, and action classes.
- Optional per-resource permissions class generation.
- Filament Shield integration to build/update `resources.manage`.
- One-shot command to run customize + permissions + Shield config.
- Configurable resource path, stub path, and permissions placement/ namespace.

Requirements

- PHP 8.3+ (8.4 supported)
- Laravel 12
- Filament 4 or 5

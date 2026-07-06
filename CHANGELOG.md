# Changelog

All notable changes to `filament-resource-customizer` will be documented in this file.

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

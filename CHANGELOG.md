# Changelog

All notable changes to `filament-resource-customizer` will be documented in this file.

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

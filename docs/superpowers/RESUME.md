# Resume Handoff — Phase 2, Sub-project A

Written 2026-07-06. Read this first when picking the work up on another machine.

## Where things stand

- **Phase 0 + Phase 1 (7 bug fixes + PHP 8.5 + Filament v4/v5 docs): DONE.** Merged to `main`,
  pushed to origin, released as **v1.2.0**. CI green (Windows + Ubuntu, PHP 8.3/8.4).
- **Phase 2 is a 4-part redesign, decomposed as A → B → C → D** (see the design docs). Only
  **sub-project A (shield-aware permission helper)** has a spec and a plan. B, C, D are not yet
  brainstormed.
- **No Phase-2 code is written yet.** This `phase-2` branch currently contains only the design
  documents (spec + plan) — carried here so the work survives the machine switch.

## Files on this branch

- Spec: `docs/superpowers/specs/2026-07-06-shield-aware-permission-helper-design.md`
- Plan: `docs/superpowers/plans/2026-07-06-shield-aware-permission-helper.md` (3 tasks, TDD)
- Prior (reference): `docs/superpowers/{specs,plans}/2026-07-06-package-hardening*.md` — the shipped
  Phase 0/1 work.

These live under `docs/`, which is gitignored on `main`; they were force-added to this branch so
they clone. If you do not want them on `main`, drop them before/at merge time.

## To continue on the other machine

1. `git fetch origin && git checkout phase-2`
2. Install deps — `composer.lock` is gitignored, so there is no lock to install from:
   `composer update` (not `composer install`).
3. Sanity-check the baseline: `composer test && composer analyse` (should be green — 26 tests).
4. Execute the plan with subagent-driven development, pointed at
   `docs/superpowers/plans/2026-07-06-shield-aware-permission-helper.md`, starting at **Task 1**.
   (In Claude Code: `/superpowers:subagent-driven-development <that plan path>`.)

## Watch-outs when executing

- **Task 2 spike:** the plan adds `bezhansalleh/filament-shield` to `require-dev` and verifies it
  boots under testbench before writing the integration test. If Shield cannot boot in testbench,
  the plan says report BLOCKED and use the documented fallback — do not fake a green test.
- Work stays on `phase-2`; do not cut a release mid-plan. v2.0.0 is cut when the Phase-2 set
  reaches a coherent milestone (A at minimum).
- After A lands, brainstorm B (panel-scoped/inheritable classes), then C (richer stub + policy
  generation), then D (round-trip shield config extras) — each its own spec → plan → implement.

## Environment note

- Package: `rolland97/filament-resource-customizer`; PHP 8.5 dev box; tests via Pest 4 + testbench
  10; static analysis PHPStan level 5. Real-world consumer for validation: `/home/rolland/projects/ewp`.

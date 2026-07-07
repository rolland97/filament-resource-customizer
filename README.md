# Filament Resource Customizer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rolland97/filament-resource-customizer.svg?style=flat-square)](https://packagist.org/packages/rolland97/filament-resource-customizer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rolland97/filament-resource-customizer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rolland97/filament-resource-customizer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rolland97/filament-resource-customizer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rolland97/filament-resource-customizer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rolland97/filament-resource-customizer.svg?style=flat-square)](https://packagist.org/packages/rolland97/filament-resource-customizer)

Customize Filament resources with optional Filament Shield permissions scaffolding.

## What it does

- Splits a Filament resource table into dedicated column, filter, and action classes.
- Generates a permissions class per resource (optional).
- Builds or updates Filament Shield `resources.manage` entries.

## Requirements

- PHP 8.3, 8.4, or 8.5
- Laravel 11 or 12
- Filament v4 or v5

This package generates and rewrites Filament resource files as source code; it does not load
Filament at runtime, so no `filament/*` package is a hard dependency. Generated output targets
APIs common to Filament v4 and v5. Full application-level compatibility across both majors is
verified manually per release (a CI matrix booting generated resources under each major is planned).

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

The panel prefix (`system:` above) is only added on Filament Shield versions that expose panel
prefixing; on versions without it the helpers return the unprefixed key (e.g.
`ViewApprovalTrail:Request`). Either way the result matches what Shield itself registered. A custom
`FilamentShield::buildPermissionKeyUsing()` closure is not honored by these helpers; they use
Shield's default key builder.

## Installation

```bash
composer require rolland97/filament-resource-customizer --dev
```

Publish the config:

```bash
php artisan vendor:publish --tag="filament-resource-customizer-config"
```

Publish stubs (optional):

```bash
php artisan vendor:publish --tag="filament-resource-customizer-stubs"
```

## Quick Start

1) Customize a resource:

```bash
php artisan filament:customize-resource DepartmentResource
```

(`DepartmentResource` is just an example — replace it with your actual resource class.)

2) Or run everything at once:

```bash
php artisan filament:customize-resource-all DepartmentResource
```

## Usage

Customize a resource by splitting tables/filters/actions into separate classes:

```bash
php artisan filament:customize-resource DepartmentResource
```

Target a specific panel:

```bash
php artisan filament:customize-resource DepartmentResource --panel=Admin
```

Generate a permissions class for a resource:

```bash
php artisan filament:make-resource-permissions DepartmentResource
```

Generate Filament Shield resources configuration:

```bash
php artisan filament:shield-config
```

Generate for a specific panel:

```bash
php artisan filament:shield-config --panel=Admin
```

Merge with existing resources:

```bash
php artisan filament:shield-config --merge
```

Run all steps (customize, permissions, and Shield config) in one command:

```bash
php artisan filament:customize-resource-all DepartmentResource
```

Run all steps for a specific panel:

```bash
php artisan filament:customize-resource-all DepartmentResource --panel=Admin
```

If `resources`, `resources.manage`, `resources.subject`, or `resources.exclude` are missing, the command will create them.

## Commands

| Command | Description |
| --- | --- |
| `filament:customize-resource` | Generate table, column, filter, and action classes |
| `filament:make-resource-permissions` | Generate only the permissions class |
| `filament:shield-config` | Update Filament Shield `resources.manage` |
| `filament:customize-resource-all` | Run customize + permissions + Shield config |

## Configuration

Key options in `config/filament-resource-customizer.php`:

- `resources_path`: Where Filament resources live; accepts a string or array (default: `app/Filament/Resources`)
- `stubs_path`: Custom stub directory (default: `stubs/filament-resource-customizer`)
- `panels.auto_detect`: Auto-detect Filament panel resource paths under `app/Filament/*/Resources` (default: `true`)
- `permissions.enabled`: Toggle permissions generation
- `permissions.placement`: `resource`, `parent`, or `custom`
- `permissions.custom_path`: Target path if placement is `custom`
- `permissions.namespace`: Override permissions namespace
- `shield.default_methods`: Default methods when no permissions class exists
- `shield.static_resources`: Always include these resources in `manage`
- `shield.merge`: Default merge behavior for `filament:shield-config`

### Permission base class

Generated permission classes extend `Rolland\FilamentResourceCustomizer\Support\BaseResourcePermissions`
by default. To make them extend a shared or custom base instead (for example, a per-app base class
that other permission classes inherit from), set `permissions.base_class`:

```php
// config/filament-resource-customizer.php
'permissions' => [
    // ...
    'base_class' => \App\Filament\Permissions\BasePermissions::class,
],
```

The configured class must exist; generation fails with a clear error otherwise. The generated file
imports it automatically (unless it lives in the generated class's own namespace).

### Generated permission methods

Generated permission classes are populated from `shield.default_methods`: each method becomes a
`SCREAMING_SNAKE` constant, and `methods()` returns those constants.

```php
class UserPermissions extends BaseResourcePermissions
{
    public const VIEW_ANY = 'viewAny';
    // ... view, create, update, delete

    public static function methods(): array
    {
        return [self::VIEW_ANY /* , ... */];
    }
}
```

Add your resource-specific permissions (e.g. `viewApprovalTrail`) as extra constants and append them
to `methods()`. Set `shield.default_methods` to `[]` to generate an empty `methods()`.

### Filament Shield integration

If you use Filament Shield, the `filament:shield-config` command will update `resources.manage`. By default it replaces entries unless you:

- pass `--merge`, or
- set `shield.merge` to `true` in the config.

**Keeping hand-maintained config across runs.** `filament:shield-config` only rewrites the
`resources.manage` array — every other section of `config/filament-shield.php` (for example
`policies.single_parameter_methods`) is left untouched. To keep extra entries inside `manage`:

- run with `--merge` (or set `shield.merge` to `true`) so existing `manage` rows are preserved
  alongside the generated ones;
- declare rows that should always be present in `shield.static_resources`
  (config: `filament-resource-customizer.shield.static_resources`) — these are injected on every
  run and survive even without `--merge`.

Note: comments written *inside* the `manage` array are not preserved when it is regenerated;
comments elsewhere in the file are kept.

### Multi-panel setups

You can use any combination of these:

- `--panel=Admin` to target a single panel for a command.
- `resources_path` as an array, e.g. `['app/Filament/Resources', 'app/Filament/Admin/Resources']`.
- `panels.auto_detect=true` to auto-add `app/Filament/*/Resources` alongside the configured paths.

## Example Structure

Running the customize command for a resource will generate classes like these:

```
app/Filament/Resources/Department/Departments/
├── DepartmentResource.php
├── DepartmentPermissions.php
└── Tables/
    ├── DepartmentsTable.php
    ├── DepartmentsColumn.php
    ├── DepartmentsFilter.php
    ├── DepartmentsRecordAction.php
    └── DepartmentsToolbarAction.php
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information.

## Upgrading

### To the shield-aware permission helper

`BaseResourcePermissions::permissions()` now returns full Shield gate strings (e.g.
`system:ViewApprovalTrail:Request`) instead of the previous `method:Resource` form, and `can()`
now returns correct results in panel-prefixed apps. If you hardcoded gate strings in your policies,
you can replace them with `YourPermissions::can('method')`. These methods now require
`bezhansalleh/filament-shield`.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

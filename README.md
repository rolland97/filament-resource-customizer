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

- PHP 8.3+ | 8.4+
- Laravel 12
- Filament 4 or 5

This package is recommended alongside Filament Shield for generating `resources.manage` entries, but it also works without Filament Shield. You can disable permission class generation by setting `permissions.enabled` to `false` in the config.

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

Generate a permissions class for a resource:

```bash
php artisan filament:make-resource-permissions DepartmentResource
```

Generate Filament Shield resources configuration:

```bash
php artisan filament:shield-config
```

Merge with existing resources:

```bash
php artisan filament:shield-config --merge
```

Run all steps (customize, permissions, and Shield config) in one command:

```bash
php artisan filament:customize-resource-all DepartmentResource
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

- `resources_path`: Where Filament resources live (default: `app/Filament/Resources`)
- `stubs_path`: Custom stub directory (default: `stubs/filament-resource-customizer`)
- `permissions.enabled`: Toggle permissions generation
- `permissions.placement`: `resource`, `parent`, or `custom`
- `permissions.custom_path`: Target path if placement is `custom`
- `permissions.namespace`: Override permissions namespace
- `shield.default_methods`: Default methods when no permissions class exists
- `shield.static_resources`: Always include these resources in `manage`
- `shield.merge`: Default merge behavior for `filament:shield-config`

### Filament Shield integration

If you use Filament Shield, the `filament:shield-config` command will update `resources.manage`. By default it replaces entries unless you:

- pass `--merge`, or
- set `shield.merge` to `true` in the config.

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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

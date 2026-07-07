<?php

use Rolland\FilamentResourceCustomizer\Support\BaseResourcePermissions;

// config for Rolland/FilamentResourceCustomizer
return [
    'resources_path' => 'app/Filament/Resources', // string or array of paths
    'stubs_path' => 'stubs/filament-resource-customizer',
    'panels' => [
        'auto_detect' => true,
        'path_template' => 'app/Filament/{panel}/Resources',
    ],
    'permissions' => [
        'enabled' => true,
        'placement' => 'resource',
        'custom_path' => null,
        'namespace' => null,
        'base_class' => BaseResourcePermissions::class,
    ],
    'shield' => [
        'default_methods' => [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
        ],
        'static_resources' => [],
        'merge' => false,
    ],
];

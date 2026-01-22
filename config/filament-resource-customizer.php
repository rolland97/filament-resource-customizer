<?php

// config for Rolland/FilamentResourceCustomizer
return [
    'resources_path' => 'app/Filament/Resources',
    'stubs_path' => 'stubs/filament-resource-customizer',
    'permissions' => [
        'enabled' => true,
        'placement' => 'resource',
        'custom_path' => null,
        'namespace' => null,
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

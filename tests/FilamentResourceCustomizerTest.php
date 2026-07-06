<?php

use Rolland\FilamentResourceCustomizer\FilamentResourceCustomizer;

it('derives panel resource path from configured template', function () {
    config(['filament-resource-customizer.panels.path_template' => 'src/Panels/{panel}/Resources']);

    $customizer = new FilamentResourceCustomizer;

    expect($customizer->resourcesPathsForPanel('Admin'))
        ->toBe([base_path('src/Panels/Admin/Resources')]);
});

it('falls back to the default filament panel path template', function () {
    $customizer = new FilamentResourceCustomizer;

    expect($customizer->resourcesPathsForPanel('Admin'))
        ->toBe([base_path('app/Filament/Admin/Resources')]);
});

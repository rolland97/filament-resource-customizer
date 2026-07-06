<?php

use Rolland\FilamentResourceCustomizer\Support\ResourceName;

it('strips only the trailing Resource suffix', function () {
    expect(ResourceName::withoutSuffix('UserResource'))->toBe('User')
        ->and(ResourceName::withoutSuffix('HumanResourceResource'))->toBe('HumanResource')
        ->and(ResourceName::withoutSuffix('ResourceResource'))->toBe('Resource')
        ->and(ResourceName::withoutSuffix('Invoice'))->toBe('Invoice')
        ->and(ResourceName::withoutSuffix('App\\Filament\\Resources\\UserResource'))->toBe('User');
});

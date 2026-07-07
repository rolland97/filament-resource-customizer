<?php

use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;
use Rolland\FilamentResourceCustomizer\Shield\ShieldGateResolver;

it('builds a shield gate string from configured case and separator', function () {
    config([
        'filament-shield.permissions.case' => 'pascal',
        'filament-shield.permissions.separator' => ':',
    ]);

    // No Filament panel is current under testbench, so prefixPermissionWithPanel
    // adds no prefix; this asserts the case + separator delegation to Shield.
    expect((new ShieldGateResolver)->key('viewApprovalTrail', 'Request'))
        ->toBe('ViewApprovalTrail:Request');
});

it('throws a clear exception when shield is unavailable', function () {
    $resolver = new class extends ShieldGateResolver
    {
        protected function shieldIsAvailable(): bool
        {
            return false;
        }
    };

    expect(fn () => $resolver->key('viewPdf', 'Request'))
        ->toThrow(RuntimeException::class, 'filament-shield is required');
});

it('is the bound default resolver', function () {
    expect(app(PermissionGateResolver::class))
        ->toBeInstanceOf(ShieldGateResolver::class);
});

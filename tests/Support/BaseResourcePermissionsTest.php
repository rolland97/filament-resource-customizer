<?php

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Rolland\FilamentResourceCustomizer\Contracts\PermissionGateResolver;
use Rolland\FilamentResourceCustomizer\Support\BaseResourcePermissions;

class TestRequestPermissions extends BaseResourcePermissions
{
    public static function getResourceName(): string
    {
        return 'Request';
    }

    public static function methods(): array
    {
        return ['viewPdf', 'submitRequest'];
    }
}

class GateTestUser extends AuthUser {}

function fakeResolver(): PermissionGateResolver
{
    return new class implements PermissionGateResolver
    {
        public function key(string $method, string $resource): string
        {
            return "gate::{$method}::{$resource}";
        }
    };
}

beforeEach(function () {
    app()->instance(PermissionGateResolver::class, fakeResolver());
});

it('builds a permission key by delegating to the resolver', function () {
    expect(TestRequestPermissions::permissionKey('viewPdf'))->toBe('gate::viewPdf::Request');
});

it('maps every method through the resolver for permissions()', function () {
    expect(TestRequestPermissions::permissions())->toBe([
        'gate::viewPdf::Request',
        'gate::submitRequest::Request',
    ]);
});

it('returns true from can() when the current user holds the gate', function () {
    Gate::define('gate::viewPdf::Request', fn ($user) => true);
    $this->actingAs(new GateTestUser);

    expect(TestRequestPermissions::can('viewPdf'))->toBeTrue();
});

it('returns false from can() when the gate is not granted', function () {
    Gate::define('gate::viewPdf::Request', fn ($user) => false);
    $this->actingAs(new GateTestUser);

    expect(TestRequestPermissions::can('viewPdf'))->toBeFalse();
});

it('returns false from can() when there is no authenticated user', function () {
    expect(TestRequestPermissions::can('viewPdf'))->toBeFalse();
});

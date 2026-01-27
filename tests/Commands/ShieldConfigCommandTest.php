<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
    File::delete(base_path('filament-shield-test.php'));
});

it('updates filament shield config resources based on permissions classes', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Organisations');
    File::ensureDirectoryExists($resourceDir);

    File::put($resourceDir.'/OrganisationResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationResource
{
}
PHP);

    File::put($resourceDir.'/OrganisationPermissions.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationPermissions
{
    public static function methods(): array
    {
        return ['custom'];
    }
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'resources' => [
        'subject' => 'model',
        'manage' => [],
        'exclude' => [],
    ],
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath])
        ->assertExitCode(0);

    $contents = File::get($configPath);

    expect($contents)
        ->toContain('resources')
        ->toContain('OrganisationResource::class');
});

it('detects resources across multiple panels when auto-detect is enabled', function () {
    config(['filament-resource-customizer.panels.auto_detect' => true]);

    $adminResourceDir = base_path('app/Filament/Admin/Resources/Vendor/Vendors');
    $customerResourceDir = base_path('app/Filament/Customer/Resources/Order/Orders');

    File::ensureDirectoryExists($adminResourceDir);
    File::ensureDirectoryExists($customerResourceDir);

    File::put($adminResourceDir.'/VendorResource.php', <<<'PHP'
<?php

namespace App\Filament\Admin\Resources\Vendor\Vendors;

class VendorResource
{
}
PHP);

    File::put($customerResourceDir.'/OrderResource.php', <<<'PHP'
<?php

namespace App\Filament\Customer\Resources\Order\Orders;

class OrderResource
{
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'resources' => [
        'subject' => 'model',
        'manage' => [],
        'exclude' => [],
    ],
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath])
        ->assertExitCode(0);

    $contents = File::get($configPath);

    expect($contents)
        ->toContain('VendorResource::class')
        ->toContain('OrderResource::class');
});

it('limits shield config generation to a specific panel', function () {
    $adminResourceDir = base_path('app/Filament/Admin/Resources/Vendor/Vendors');
    $customerResourceDir = base_path('app/Filament/Customer/Resources/Order/Orders');

    File::ensureDirectoryExists($adminResourceDir);
    File::ensureDirectoryExists($customerResourceDir);

    File::put($adminResourceDir.'/VendorResource.php', <<<'PHP'
<?php

namespace App\Filament\Admin\Resources\Vendor\Vendors;

class VendorResource
{
}
PHP);

    File::put($customerResourceDir.'/OrderResource.php', <<<'PHP'
<?php

namespace App\Filament\Customer\Resources\Order\Orders;

class OrderResource
{
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'resources' => [
        'subject' => 'model',
        'manage' => [],
        'exclude' => [],
    ],
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath, '--panel' => 'Admin'])
        ->assertExitCode(0);

    $contents = File::get($configPath);

    expect($contents)
        ->toContain('VendorResource::class')
        ->not->toContain('OrderResource::class');
});

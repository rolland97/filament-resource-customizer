<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
    File::delete(base_path('filament-shield-test.php'));
});

it('merges resources when the merge option is used', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Organisations');
    File::ensureDirectoryExists($resourceDir);

    File::put($resourceDir.'/OrganisationResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationResource
{
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'resources' => [
        'subject' => 'model',
        'manage' => [
            \App\Filament\Resources\Organisation\Organisations\ExistingResource::class => ['viewAny'],
        ],
        'exclude' => [],
    ],
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath, '--merge' => true])
        ->assertExitCode(0);

    $contents = File::get($configPath);

    expect($contents)
        ->toContain('ExistingResource::class')
        ->toContain('OrganisationResource::class');
});

it('merges resources when the config default is enabled', function () {
    config(['filament-resource-customizer.shield.merge' => true]);

    $resourceDir = base_path('app/Filament/Resources/Organisation/Organisations');
    File::ensureDirectoryExists($resourceDir);

    File::put($resourceDir.'/OrganisationResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationResource
{
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'resources' => [
        'subject' => 'model',
        'manage' => [
            \App\Filament\Resources\Organisation\Organisations\ExistingResource::class => ['viewAny'],
        ],
        'exclude' => [],
    ],
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath])
        ->assertExitCode(0);

    $contents = File::get($configPath);

    expect($contents)
        ->toContain('ExistingResource::class')
        ->toContain('OrganisationResource::class');
});

it('preserves single_parameter_methods and hand-added manage rows on a merge run', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Organisations');
    File::ensureDirectoryExists($resourceDir);

    File::put($resourceDir.'/OrganisationResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationResource
{
}
PHP);

    $configPath = base_path('filament-shield-test.php');

    File::put($configPath, <<<'PHP'
<?php

return [
    'resources' => [
        'subject' => 'model',
        'manage' => [
            \App\Support\BaseApprovalResource::class => ['viewAny', 'view'],
        ],
        'exclude' => [],
    ],
    'policies' => [
        'single_parameter_methods' => [
            'viewPdf',
            'restoreWithRelationsAny',
        ],
    ],
];
PHP);

    $this->artisan('filament:shield-config', ['--path' => $configPath, '--merge' => true])
        ->assertExitCode(0);

    $contents = File::get($configPath);

    expect($contents)
        // newly discovered resource added
        ->toContain('OrganisationResource::class')
        // hand-added base-resource row preserved under merge
        ->toContain('BaseApprovalResource::class')
        // section outside resources.manage left untouched
        ->toContain('single_parameter_methods')
        ->toContain('viewPdf')
        ->toContain('restoreWithRelationsAny');
});

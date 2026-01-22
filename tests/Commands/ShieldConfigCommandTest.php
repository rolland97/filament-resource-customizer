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

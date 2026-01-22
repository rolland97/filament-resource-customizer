<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('generates a permissions file for a resource', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Organisations');
    File::ensureDirectoryExists($resourceDir);

    File::put($resourceDir.'/OrganisationResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Organisations;

class OrganisationResource
{
}
PHP);

    $this->artisan('filament:make-resource-permissions OrganisationResource')
        ->assertExitCode(0);

    expect(File::exists($resourceDir.'/OrganisationPermissions.php'))->toBeTrue();
});

it('fails when the permissions file already exists without force', function () {
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
}
PHP);

    $this->artisan('filament:make-resource-permissions OrganisationResource')
        ->assertExitCode(1);
});

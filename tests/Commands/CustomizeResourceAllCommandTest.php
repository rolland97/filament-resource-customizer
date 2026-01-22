<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
    File::delete(base_path('filament-shield-test.php'));
});

it('customizes a resource, generates permissions, and updates shield config', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Users');
    $tablesDir = $resourceDir.'/Tables';

    File::ensureDirectoryExists($tablesDir);

    File::put($resourceDir.'/UserResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users;

class UserResource
{
}
PHP);

    File::put($tablesDir.'/UsersTable.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
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

    $this->artisan('filament:customize-resource-all', ['resource' => 'UserResource', '--path' => $configPath])
        ->assertExitCode(0);

    expect(File::exists($resourceDir.'/UserPermissions.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersColumn.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersFilter.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersRecordAction.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersToolbarAction.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersTable.php'))->toBeTrue()
        ->and(File::get($configPath))->toContain('UserResource::class');
});

it('fails when the shield config file is missing', function () {
    $resourceDir = base_path('app/Filament/Resources/Organisation/Users');
    $tablesDir = $resourceDir.'/Tables';

    File::ensureDirectoryExists($tablesDir);

    File::put($resourceDir.'/UserResource.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users;

class UserResource
{
}
PHP);

    File::put($tablesDir.'/UsersTable.php', <<<'PHP'
<?php

namespace App\Filament\Resources\Organisation\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
PHP);

    $this->artisan('filament:customize-resource-all', ['resource' => 'UserResource'])
        ->assertExitCode(1);

    expect(File::exists($resourceDir.'/UserPermissions.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersColumn.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersFilter.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersRecordAction.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersToolbarAction.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersTable.php'))->toBeTrue();
});

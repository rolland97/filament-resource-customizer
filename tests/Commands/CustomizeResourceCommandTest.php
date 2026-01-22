<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('customizes a resource by generating table and permission files', function () {
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

    $this->artisan('filament:customize-resource UserResource')
        ->assertExitCode(0);

    expect(File::exists($tablesDir.'/UsersColumn.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersFilter.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersRecordAction.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersToolbarAction.php'))->toBeTrue()
        ->and(File::exists($tablesDir.'/UsersTable.php'))->toBeTrue()
        ->and(File::exists($resourceDir.'/UserPermissions.php'))->toBeFalse();
});

it('imports fully qualified actions when generating action classes', function () {
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

use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([])
            ->filters([])
            ->recordActions([
                \App\Filament\Tables\Actions\ImpersonateUserAction::make(),
            ])
            ->toolbarActions([]);
    }
}
PHP);

    $this->artisan('filament:customize-resource UserResource')
        ->assertExitCode(0);

    $recordActionPath = $tablesDir.'/UsersRecordAction.php';
    $recordActionContents = File::get($recordActionPath);

    expect($recordActionContents)
        ->toContain('use App\\Filament\\Tables\\Actions\\ImpersonateUserAction;')
        ->toContain('ImpersonateUserAction::make()');
});

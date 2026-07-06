<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

function seedPristineUserResource(): array
{
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

    return [$resourceDir, $tablesDir];
}

it('refuses to re-customize and preserves generated files (no --force)', function () {
    [, $tablesDir] = seedPristineUserResource();

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);

    $columnBefore = File::get($tablesDir.'/UsersColumn.php');

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(1);

    expect(File::get($tablesDir.'/UsersColumn.php'))->toBe($columnBefore);
});

it('refuses to re-customize even with --force', function () {
    [, $tablesDir] = seedPristineUserResource();

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);
    $columnBefore = File::get($tablesDir.'/UsersColumn.php');

    $this->artisan('filament:customize-resource UserResource --force')->assertExitCode(1);

    expect(File::get($tablesDir.'/UsersColumn.php'))->toBe($columnBefore);
});

it('refuses re-customize when sibling files were deleted but the table is already templated', function () {
    [, $tablesDir] = seedPristineUserResource();

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);

    // Simulate a partial cleanup: sibling generated files gone, table still templated.
    File::delete($tablesDir.'/UsersColumn.php');
    File::delete($tablesDir.'/UsersFilter.php');
    File::delete($tablesDir.'/UsersRecordAction.php');
    File::delete($tablesDir.'/UsersToolbarAction.php');

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(1);
});

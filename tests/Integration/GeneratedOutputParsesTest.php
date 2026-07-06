<?php

use Illuminate\Support\Facades\File;
use PhpParser\ParserFactory;

beforeEach(function () {
    File::deleteDirectory(base_path('app'));
});

it('generates syntactically valid PHP for every customized file', function () {
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

    $this->artisan('filament:customize-resource UserResource')->assertExitCode(0);

    $parser = (new ParserFactory)->createForHostVersion();

    foreach (File::allFiles($tablesDir) as $file) {
        $code = File::get($file->getPathname());
        expect(fn () => $parser->parse($code))->not->toThrow(Throwable::class);
        expect($parser->parse($code))->not->toBeNull();
    }
});

<?php

namespace App\Filament\Resources\Users;

use App\Actions\Auth\RegisterUserAction;
use App\Actions\User\UpdateUserAction;
use App\Data\Auth\RegisterUserData;
use App\Data\User\UpdateUserData;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'user';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email']; // <-- make sure these exist in your users table
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name; // or combine: "$record->name ($record->email)"
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE USER
    |--------------------------------------------------------------------------
    */

    /**
     * @throws Throwable
     */
    protected function handleRecordCreate(array $data): Model
    {
        $dto = RegisterUserData::from($data);

        $user = RegisterUserAction::run($dto);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $dto = UpdateUserData::from($data);

        $user = UpdateUserAction::run($record, $dto);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    protected function afterCreate(Model $record, array $data): void
    {
        // 3. Assign Roles (Action doesn't do this)
        if (isset($data['roles']) && is_array($data['roles'])) {
            $record->roles()->sync($data['roles']);
        }
    }
}

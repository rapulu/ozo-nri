<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Member;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Leave blank to keep the current password.'),
                Select::make('role')
                    ->options([
                        User::ROLE_ADMIN => 'Secretary / admin',
                        User::ROLE_MEMBER => 'Member',
                    ])
                    ->required()
                    ->default(User::ROLE_MEMBER)
                    ->reactive(),
                Select::make('member_id')
                    ->label('Linked member')
                    ->options(fn (): array => Member::query()->orderBy('last_name')->get()->mapWithKeys(fn (Member $m): array => [$m->id => $m->full_name])->toArray())
                    ->searchable()
                    ->helperText('Link their association profile: members keep their own portal even as admins; admins without a link have no portal.'),
            ]);
    }
}

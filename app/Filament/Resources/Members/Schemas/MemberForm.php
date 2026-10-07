<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\MemberStatus;
use App\Enums\MemberTitle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->schema([
                        Select::make('title')
                            ->options(MemberTitle::options())
                            ->required()
                            ->searchable(),
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('middle_name')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(MemberStatus::options())
                            ->required()
                            ->default(MemberStatus::Active->value),
                    ])
                    ->columns(2),
                Section::make('Contact & Login')
                    ->schema([
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
                            ->maxLength(255)
                            ->helperText('Leave blank to keep existing password when editing.'),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Membership')
                    ->schema([
                        DatePicker::make('date_joined')
                            ->default(now())
                            ->maxDate(now()),
                        TextInput::make('opening_arrears')
                            ->label('Opening arrears (₦)')
                            ->numeric()
                            ->stripCharacters([',', ' '])
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Past money this member owes from before these records began. Counts toward their outstanding.'),
                        FileUpload::make('photo_path')
                            ->label('Photo')
                            ->image()
                            ->directory('member-photos')
                            ->maxSize(2048),
                        Textarea::make('notes')
                            ->columnSpanFull()
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }
}

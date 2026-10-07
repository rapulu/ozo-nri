<?php

namespace App\Filament\Resources\CondolenceLevies\Schemas;

use App\Enums\LevyStatus;
use App\Models\Condolence;
use App\Models\Member;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CondolenceLevyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('condolence_id')
                    ->label('Condolence')
                    ->options(fn (): array => Condolence::query()->pluck('title', 'id')->toArray())
                    ->searchable()
                    ->required(),
                Select::make('member_id')
                    ->label('Member')
                    ->options(fn (): array => Member::query()->orderBy('last_name')->get()->mapWithKeys(fn (Member $m): array => [$m->id => $m->full_name])->toArray())
                    ->searchable()
                    ->required(),
                TextInput::make('amount_expected')
                    ->label('Expected (₦)')
                    ->required()
                    ->numeric()
                    ->stripCharacters([',', ' '])
                    ->minValue(0),
                TextInput::make('amount_paid')
                    ->label('Paid (₦)')
                    ->numeric()
                    ->stripCharacters([',', ' '])
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Paid total is calculated from payments. Record a payment instead.'),
                Select::make('status')
                    ->options(LevyStatus::options())
                    ->required()
                    ->default(LevyStatus::Unpaid->value),
            ]);
    }
}

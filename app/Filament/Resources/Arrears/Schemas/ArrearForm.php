<?php

namespace App\Filament\Resources\Arrears\Schemas;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use App\Models\Condolence;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArrearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Arrear')
                    ->schema([
                        Select::make('member_id')
                            ->label('Member')
                            ->options(fn (): array => Member::query()->where('status', MemberStatus::Active->value)->orderBy('last_name')->orderBy('first_name')->get()->mapWithKeys(fn (Member $m): array => [$m->id => $m->full_name])->toArray())
                            ->searchable()
                            ->required(),
                        Select::make('reason')
                            ->options(ArrearReason::options())
                            ->required()
                            ->reactive()
                            ->default(ArrearReason::Other->value),
                        TextInput::make('title')
                            ->label('Title (e.g. 2026 annual dues)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('condolence_id')
                            ->label('Condolence')
                            ->options(fn (): array => Condolence::query()->pluck('title', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (callable $get): bool => $get('reason') === ArrearReason::Condolence->value)
                            ->required(fn (callable $get): bool => $get('reason') === ArrearReason::Condolence->value)
                            ->helperText('Required when the reason is condolence.'),
                        TextInput::make('amount_expected')
                            ->label('Arrears amount (₦)')
                            ->required()
                            ->numeric()
                            ->stripCharacters([',', ' '])
                            ->minValue(0),
                        DatePicker::make('due_date')
                            ->label('Due date'),
                        Select::make('status')
                            ->options(LevyStatus::options())
                            ->required()
                            ->default(LevyStatus::Unpaid->value),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

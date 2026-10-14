<?php

namespace App\Filament\Resources\Condolences\RelationManagers;

use App\Enums\LevyStatus;
use App\Models\CondolenceLevy;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeviesRelationManager extends RelationManager
{
    protected static string $relationship = 'levies';

    protected static ?string $title = 'Member levies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount_expected')
                    ->label('Levy amount (₦)')
                    ->required()
                    ->numeric()
                    ->stripCharacters([',', ' '])
                    ->minValue(0),
                Select::make('status')
                    ->options(LevyStatus::options())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Member')
                    ->getStateUsing(fn (CondolenceLevy $record): string => $record->member?->full_name ?? '—')
                    ->searchable(),
                TextColumn::make('carried_arrears')
                    ->label('Arrears (₦)')
                    ->getStateUsing(fn (CondolenceLevy $record): float => $record->carriedArrears())
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('amount_expected')
                    ->label('Levy (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Received (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('running_outstanding')
                    ->label('Outstanding (₦)')
                    ->getStateUsing(fn (CondolenceLevy $record): float => $record->runningOutstanding())
                    ->money('NGN'),
                TextColumn::make('last_payment_date')
                    ->label('Last paid')
                    ->getStateUsing(fn (CondolenceLevy $record): ?string => $record->payments()->max('paid_at'))
                    ->date()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        LevyStatus::Paid->value => 'success',
                        LevyStatus::Partial->value => 'warning',
                        LevyStatus::Unpaid->value => 'danger',
                        LevyStatus::Exempted->value => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LevyStatus::options()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit arrears')
                    ->after(fn (CondolenceLevy $record): mixed => $record->recalculate()),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CondolenceLevies\Tables;

use App\Enums\LevyStatus;
use App\Models\Condolence;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CondolenceLeviesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('condolence.title')
                    ->label('Condolence')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('member.full_name')
                    ->label('Member')
                    ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—')
                    ->searchable(),
                TextColumn::make('amount_expected')
                    ->label('Expected')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->getStateUsing(fn ($record): float => (float) $record->amount_expected - (float) $record->amount_paid)
                    ->money('NGN'),
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
                SelectFilter::make('condolence_id')
                    ->label('Condolence')
                    ->options(fn (): array => Condolence::query()->pluck('title', 'id')->toArray())
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(LevyStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

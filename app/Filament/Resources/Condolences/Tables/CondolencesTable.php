<?php

namespace App\Filament\Resources\Condolences\Tables;

use App\Enums\CondolenceStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CondolencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('deceasedMember.full_name')
                    ->label('Deceased')
                    ->getStateUsing(fn ($record): ?string => $record->deceasedMember?->full_name)
                    ->searchable(),
                TextColumn::make('amount_per_member')
                    ->label('Per member')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('levies_count')
                    ->label('Members levied')
                    ->counts('levies')
                    ->sortable(),
                TextColumn::make('collected')
                    ->label('Collected')
                    ->getStateUsing(fn ($record): float => (float) $record->levies()->sum('amount_paid'))
                    ->money('NGN'),
                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->getStateUsing(function ($record): float {
                        $expected = (float) $record->levies()->sum('amount_expected');
                        $paid = (float) $record->levies()->sum('amount_paid');

                        return $expected - $paid;
                    })
                    ->money('NGN'),
                TextColumn::make('date_announced')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CondolenceStatus::Open->value => 'success',
                        CondolenceStatus::Closed->value => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CondolenceStatus::options()),
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

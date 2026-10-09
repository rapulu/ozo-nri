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
                TextColumn::make('amount_per_member')
                    ->label('Per member')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('levies_count')
                    ->label('Members levied')
                    ->counts('levies')
                    ->sortable(),
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

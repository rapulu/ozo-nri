<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\MemberStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl(fn (): string => 'https://ui-avatars.com/api/?name='.urlencode('M').'&color=7F9CF5&background=EBF4FF'),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn ($record): string => $record->full_name)
                    ->searchable(query: function ($query, string $search) {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%");
                    })
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MemberStatus::Active->value => 'success',
                        MemberStatus::Suspended->value => 'warning',
                        MemberStatus::Deceased->value => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('date_joined')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('levies_sum_outstanding')
                    ->label('Outstanding')
                    ->getStateUsing(function ($record): float {
                        $expected = (float) $record->levies()->sum('amount_expected');
                        $paid = (float) $record->levies()->sum('amount_paid');

                        return $expected - $paid;
                    })
                    ->money('NGN')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MemberStatus::options()),
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

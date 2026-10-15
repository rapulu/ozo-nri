<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => $state === User::ROLE_ADMIN ? 'warning' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === User::ROLE_ADMIN ? 'Secretary / admin' : 'Member'),
                TextColumn::make('member.full_name')
                    ->label('Linked member')
                    ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        User::ROLE_ADMIN => 'Secretary / admin',
                        User::ROLE_MEMBER => 'Member',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

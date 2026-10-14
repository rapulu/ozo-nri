<?php

namespace App\Filament\Resources\Condolences\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CondolenceDepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'deposits';

    protected static ?string $title = 'Payment records (member deposits)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deposit')
                    ->schema([
                        TextEntry::make('member.full_name')
                            ->label('Member')
                            ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—'),
                        TextEntry::make('amount')
                            ->label('Deposited')
                            ->money('NGN'),
                        TextEntry::make('pivot.amount')
                            ->label('Applied here')
                            ->money('NGN'),
                        TextEntry::make('paid_at')
                            ->label('Date received')
                            ->date(),
                        TextEntry::make('payment_method')
                            ->badge(),
                        TextEntry::make('reference')
                            ->label('Reference'),
                        TextEntry::make('recorder.name')
                            ->label('Recorded by'),
                        TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Member')
                    ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—')
                    ->searchable(),
                TextColumn::make('pivot.amount')
                    ->label('Applied here')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Deposit total')
                    ->money('NGN')
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->label('Date received')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '—')
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}

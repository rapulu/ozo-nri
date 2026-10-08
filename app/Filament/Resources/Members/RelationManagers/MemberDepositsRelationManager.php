<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\ArrearReason;
use App\Filament\Resources\Members\Actions\LogMemberPayment;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberDepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'deposits';

    protected static ?string $title = 'Payment history (bulk deposits)';

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
                        TextEntry::make('amount')
                            ->money('NGN'),
                        TextEntry::make('paid_at')
                            ->label('Date received')
                            ->date(),
                        TextEntry::make('payment_method')
                            ->badge(),
                        TextEntry::make('reason')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? (ArrearReason::options()[$state] ?? $state) : '—'),
                        TextEntry::make('reference')
                            ->label('Reference'),
                        TextEntry::make('outstanding_before')
                            ->label('Arrears before payment')
                            ->money('NGN'),
                        TextEntry::make('outstanding_after')
                            ->label('Outstanding after payment')
                            ->money('NGN'),
                        TextEntry::make('opening_applied')
                            ->label('Of which to opening balance')
                            ->money('NGN'),
                        TextEntry::make('credit_added')
                            ->label('Of which kept as credit')
                            ->money('NGN'),
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
                TextColumn::make('amount')
                    ->label('Deposited')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date received')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                LogMemberPayment::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}

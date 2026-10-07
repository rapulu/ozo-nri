<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\ArrearReason;
use App\Enums\PaymentMethod;
use App\Models\Condolence;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
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
                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date received')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (ArrearReason::options()[$state] ?? $state) : '—')
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Receipt no.')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('recorder.name')
                    ->label('Recorded by')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('condolence_id')
                    ->label('Condolence')
                    ->options(fn (): array => Condolence::query()->pluck('title', 'id')->toArray())
                    ->searchable(),
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::options()),
                SelectFilter::make('reason')
                    ->options(ArrearReason::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}

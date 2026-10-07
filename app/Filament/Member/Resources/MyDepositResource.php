<?php

namespace App\Filament\Member\Resources;

use App\Enums\ArrearReason;
use App\Models\Deposit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyDepositResource extends Resource
{
    protected static ?string $model = Deposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'My Payment History';

    protected static ?string $modelLabel = 'My Payment';

    public static function getEloquentQuery(): Builder
    {
        $memberId = Auth::guard('member')->id();

        return parent::getEloquentQuery()->where('member_id', $memberId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Paid')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date paid')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (ArrearReason::options()[$state] ?? $state) : '—'),
                TextColumn::make('reference')
                    ->label('Reference'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyDeposits::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

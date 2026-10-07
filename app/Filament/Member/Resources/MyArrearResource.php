<?php

namespace App\Filament\Member\Resources;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use App\Models\Arrear;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyArrearResource extends Resource
{
    protected static ?string $model = Arrear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationCircle;

    protected static ?string $navigationLabel = 'My Arrears';

    protected static ?string $modelLabel = 'My Arrear';

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
                TextColumn::make('title')
                    ->limit(30),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ArrearReason::options()[$state] ?? $state),
                TextColumn::make('amount_expected')
                    ->label('Arrears')
                    ->money('NGN'),
                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('NGN'),
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
                        default => 'gray',
                    }),
                TextColumn::make('due_date')
                    ->date()
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyArrears::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

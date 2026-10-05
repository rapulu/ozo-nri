<?php

namespace App\Filament\Member\Resources;

use App\Enums\LevyStatus;
use App\Models\CondolenceLevy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyLevyResource extends Resource
{
    protected static ?string $model = CondolenceLevy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'My Condolence Levies';

    protected static ?string $modelLabel = 'My Levy';

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
                TextColumn::make('condolence.title')
                    ->label('Condolence')
                    ->wrap(),
                TextColumn::make('condolence.date_announced')
                    ->label('Announced')
                    ->date(),
                TextColumn::make('amount_expected')
                    ->label('Expected')
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
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyLevies::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

<?php

namespace App\Filament\Resources\Arrears\Schemas;

use App\Enums\ArrearReason;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArrearInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Arrear')
                    ->schema([
                        TextEntry::make('member.full_name')
                            ->label('Member')
                            ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—'),
                        TextEntry::make('reason')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ArrearReason::options()[$state] ?? $state),
                        TextEntry::make('title'),
                        TextEntry::make('condolence.title')
                            ->label('Condolence')
                            ->placeholder('—'),
                        TextEntry::make('amount_expected')
                            ->money('NGN'),
                        TextEntry::make('amount_paid')
                            ->money('NGN'),
                        TextEntry::make('balance')
                            ->label('Outstanding')
                            ->getStateUsing(fn ($record): float => (float) $record->amount_expected - (float) $record->amount_paid)
                            ->money('NGN'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('due_date')
                            ->date(),
                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

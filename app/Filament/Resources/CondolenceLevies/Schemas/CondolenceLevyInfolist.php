<?php

namespace App\Filament\Resources\CondolenceLevies\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CondolenceLevyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Obligation')
                    ->schema([
                        TextEntry::make('condolence.title')
                            ->label('Condolence'),
                        TextEntry::make('member.full_name')
                            ->label('Member')
                            ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—'),
                        TextEntry::make('amount_expected')
                            ->money('NGN'),
                        TextEntry::make('amount_paid')
                            ->money('NGN'),
                        TextEntry::make('balance')
                            ->label('Balance')
                            ->getStateUsing(fn ($record): float => (float) $record->amount_expected - (float) $record->amount_paid)
                            ->money('NGN'),
                        TextEntry::make('status')
                            ->badge(),
                    ])
                    ->columns(2),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Condolences\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CondolenceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Condolence')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('deceasedMember.full_name')
                            ->label('Deceased member')
                            ->getStateUsing(fn ($record): ?string => $record->deceasedMember?->full_name),
                        TextEntry::make('amount_per_member')
                            ->money('NGN'),
                        TextEntry::make('date_announced')
                            ->date(),
                        TextEntry::make('due_date')
                            ->date(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Totals')
                    ->schema([
                        TextEntry::make('expected')
                            ->label('Total expected')
                            ->getStateUsing(fn ($record): float => (float) $record->levies()->sum('amount_expected'))
                            ->money('NGN'),
                        TextEntry::make('collected')
                            ->label('Total collected')
                            ->getStateUsing(fn ($record): float => (float) $record->levies()->sum('amount_paid'))
                            ->money('NGN'),
                        TextEntry::make('outstanding')
                            ->label('Outstanding')
                            ->getStateUsing(function ($record): float {
                                $expected = (float) $record->levies()->sum('amount_expected');
                                $paid = (float) $record->levies()->sum('amount_paid');

                                return $expected - $paid;
                            })
                            ->money('NGN'),
                    ])
                    ->columns(3),
            ]);
    }
}

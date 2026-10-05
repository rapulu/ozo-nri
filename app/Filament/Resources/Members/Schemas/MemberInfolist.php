<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->schema([
                        ImageEntry::make('photo_path')
                            ->label('Photo')
                            ->circular(),
                        TextEntry::make('full_name')
                            ->label('Full name')
                            ->getStateUsing(fn ($record): string => $record->full_name),
                        TextEntry::make('email'),
                        TextEntry::make('phone'),
                        TextEntry::make('address'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('date_joined')
                            ->date(),
                    ])
                    ->columns(2),
                Section::make('Account summary')
                    ->schema([
                        TextEntry::make('total_expected')
                            ->label('Total expected')
                            ->getStateUsing(fn ($record): float => (float) $record->levies()->sum('amount_expected'))
                            ->money('NGN'),
                        TextEntry::make('total_paid')
                            ->label('Total paid')
                            ->getStateUsing(fn ($record): float => (float) $record->levies()->sum('amount_paid'))
                            ->money('NGN'),
                        TextEntry::make('total_outstanding')
                            ->label('Outstanding')
                            ->getStateUsing(function ($record): float {
                                $expected = (float) $record->levies()->sum('amount_expected');
                                $paid = (float) $record->levies()->sum('amount_paid');

                                return $expected - $paid;
                            })
                            ->money('NGN'),
                        TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}

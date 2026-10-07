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
                Section::make('Account summary (condolences + arrears)')
                    ->schema([
                        TextEntry::make('opening_arrears')
                            ->label('Opening arrears')
                            ->money('NGN')
                            ->helperText('Past debt — edit on the Edit page.'),
                        TextEntry::make('total_expected')
                            ->label('New levies')
                            ->getStateUsing(fn ($record): float => $record->leviesOwing())
                            ->money('NGN')
                            ->helperText('Condolence levies still owing only.'),
                        TextEntry::make('total_paid')
                            ->label('Total paid')
                            ->getStateUsing(fn ($record): float => $record->accountTotals()['paid'])
                            ->money('NGN'),
                        TextEntry::make('total_outstanding')
                            ->label('Outstanding')
                            ->getStateUsing(fn ($record): float => $record->accountTotals()['outstanding'])
                            ->money('NGN'),
                        TextEntry::make('credit_balance')
                            ->label('Credit (owed to member)')
                            ->money('NGN')
                            ->helperText('Overpayments sit here and are eaten by future levies automatically.'),
                        TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}

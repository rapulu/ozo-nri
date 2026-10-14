<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment')
                    ->schema([
                        TextEntry::make('condolence.title')
                            ->label('Condolence'),
                        TextEntry::make('member.full_name')
                            ->label('Member')
                            ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—'),
                        TextEntry::make('amount')
                            ->money('NGN'),
                        TextEntry::make('paid_at')
                            ->label('Date received')
                            ->date(),
                        TextEntry::make('payment_method')
                            ->badge(),
                        TextEntry::make('reference')
                            ->label('Receipt / reference'),
                        TextEntry::make('recorder.name')
                            ->label('Recorded by'),
                        TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Arrears\RelationManagers;

use App\Enums\ArrearReason;
use App\Enums\PaymentMethod;
use App\Models\ArrearPayment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArrearPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Part-payment history';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Amount received (₦)')
                    ->required()
                    ->numeric()
                    ->stripCharacters([',', ' '])
                    ->minValue(1),
                DatePicker::make('paid_at')
                    ->label('Date received')
                    ->required()
                    ->maxDate(now()),
                Select::make('payment_method')
                    ->options(PaymentMethod::options())
                    ->required(),
                Select::make('reason')
                    ->options(ArrearReason::options())
                    ->required(),
                TextInput::make('reference')
                    ->label('Receipt / reference no.')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment')
                    ->schema([
                        TextEntry::make('amount')
                            ->money('NGN'),
                        TextEntry::make('paid_at')
                            ->label('Date received')
                            ->date(),
                        TextEntry::make('payment_method')
                            ->badge(),
                        TextEntry::make('reason')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? (ArrearReason::options()[$state] ?? $state) : '—'),
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date received')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (ArrearReason::options()[$state] ?? $state) : '—'),
                TextColumn::make('reference')
                    ->label('Receipt no.')
                    ->toggleable(),
                TextColumn::make('recorder.name')
                    ->label('Recorded by')
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        /** @var ArrearPayment $owner */
                        $data['member_id'] = $this->getOwnerRecord()->member_id;
                        $data['recorded_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}

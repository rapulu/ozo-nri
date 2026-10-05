<?php

namespace App\Filament\Resources\Condolences\RelationManagers;

use App\Enums\LevyStatus;
use App\Enums\PaymentMethod;
use App\Models\CondolenceLevy;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeviesRelationManager extends RelationManager
{
    protected static string $relationship = 'levies';

    protected static ?string $title = 'Member levies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount_expected')
                    ->label('Arrears / expected (₦)')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Select::make('status')
                    ->options(LevyStatus::options())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Member')
                    ->getStateUsing(fn (CondolenceLevy $record): string => $record->member?->full_name ?? '—')
                    ->searchable(),
                TextColumn::make('amount_expected')
                    ->label('Arrears (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Received (₦)')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Outstanding (₦)')
                    ->getStateUsing(fn (CondolenceLevy $record): float => (float) $record->amount_expected - (float) $record->amount_paid)
                    ->money('NGN'),
                TextColumn::make('last_payment_date')
                    ->label('Last paid')
                    ->getStateUsing(fn (CondolenceLevy $record): ?string => $record->payments()->max('paid_at'))
                    ->date()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        LevyStatus::Paid->value => 'success',
                        LevyStatus::Partial->value => 'warning',
                        LevyStatus::Unpaid->value => 'danger',
                        LevyStatus::Exempted->value => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LevyStatus::options()),
            ])
            ->recordActions([
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount received (₦)')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        DatePicker::make('paid_at')
                            ->label('Date received')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        Select::make('payment_method')
                            ->options(PaymentMethod::options())
                            ->required()
                            ->default(PaymentMethod::Cash->value),
                        TextInput::make('reference')
                            ->label('Receipt / reference no.')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->action(function (CondolenceLevy $record, array $data): void {
                        Payment::create([
                            'condolence_levy_id' => $record->id,
                            'condolence_id' => $record->condolence_id,
                            'member_id' => $record->member_id,
                            'amount' => $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $data['reference'] ?? null,
                            'recorded_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);
                    }),
                EditAction::make()
                    ->label('Edit arrears')
                    ->after(fn (CondolenceLevy $record): mixed => $record->recalculate()),
            ]);
    }
}

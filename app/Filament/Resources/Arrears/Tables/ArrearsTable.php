<?php

namespace App\Filament\Resources\Arrears\Tables;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use App\Enums\PaymentMethod;
use App\Models\Arrear;
use App\Models\ArrearPayment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArrearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Member')
                    ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—')
                    ->searchable(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ArrearReason::options()[$state] ?? $state),
                TextColumn::make('title')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('amount_expected')
                    ->label('Arrears')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Received')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Outstanding')
                    ->getStateUsing(fn ($record): float => (float) $record->amount_expected - (float) $record->amount_paid)
                    ->money('NGN'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        LevyStatus::Paid->value => 'success',
                        LevyStatus::Partial->value => 'warning',
                        LevyStatus::Unpaid->value => 'danger',
                        LevyStatus::Exempted->value => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->options(ArrearReason::options()),
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
                            ->stripCharacters([',', ' '])
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
                        Select::make('reason')
                            ->options(ArrearReason::options())
                            ->required()
                            ->default(fn (Arrear $record): string => $record->reason),
                        TextInput::make('reference')
                            ->label('Receipt / reference no.')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->action(function (Arrear $record, array $data): void {
                        ArrearPayment::create([
                            'arrear_id' => $record->id,
                            'member_id' => $record->member_id,
                            'amount' => $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $data['reference'] ?? null,
                            'reason' => $data['reason'],
                            'recorded_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('due_date');
    }
}

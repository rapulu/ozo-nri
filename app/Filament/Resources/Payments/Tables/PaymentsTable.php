<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\ArrearReason;
use App\Enums\PaymentMethod;
use App\Models\Condolence;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Member')
                    ->getStateUsing(fn ($record): string => $record->member?->full_name ?? '—')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (ArrearReason::options()[$state] ?? $state) : '—'),
            ])
            ->filters([
                SelectFilter::make('condolence_id')
                    ->label('Condolence')
                    ->options(fn (): array => Condolence::query()->pluck('title', 'id')->toArray())
                    ->searchable(),
                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::options()),
                SelectFilter::make('reason')
                    ->options(ArrearReason::options()),
            ])
            ->recordActions([
                Action::make('viewReceipt')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(false)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalContent(function (Payment $record): View {
                        $levy = $record->levy;

                        return view('filament.payment-receipt', [
                            'member' => $record->member,
                            'payment' => $record,
                            'appliedTo' => $levy?->condolence?->title ?? 'Condolence levy',
                            'balanceAfter' => $levy
                                ? max(0, (float) $levy->amount_expected - (float) $levy->amount_paid)
                                : 0,
                        ]);
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}

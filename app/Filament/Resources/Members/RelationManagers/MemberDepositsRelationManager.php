<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\ArrearReason;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Members\Actions\LogMemberPayment;
use App\Models\Arrear;
use App\Models\ArrearPayment;
use App\Models\CondolenceLevy;
use App\Models\Deposit;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class MemberDepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'deposits';

    protected static ?string $title = 'Payment history (bulk deposits)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Amount deposited (₦)')
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
                    ->label('Reference')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Deposited')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date received')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                LogMemberPayment::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewReceipt')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(false)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalContent(function (Deposit $record): View {
                        $member = $record->member;

                        $splits = Payment::where('member_id', $member->id)
                            ->where('reference', $record->reference)
                            ->with('condolence')
                            ->get()
                            ->map(fn (Payment $p): array => [
                                'label' => $p->condolence?->title ?? 'Condolence levy',
                                'amount' => (float) $p->amount,
                            ])
                            ->all();

                        foreach (
                            ArrearPayment::where('member_id', $member->id)
                                ->where('reference', $record->reference)
                                ->with('arrear')
                                ->get() as $p
                        ) {
                            $splits[] = [
                                'label' => $p->arrear?->title ?? 'Arrear',
                                'amount' => (float) $p->amount,
                            ];
                        }

                        return view('filament.deposit-receipt', [
                            'member' => $member,
                            'deposit' => $record,
                            'splits' => $splits,
                            'lastArrearDate' => collect([
                                CondolenceLevy::where('member_id', $member->id)->max('created_at'),
                                Arrear::where('member_id', $member->id)->max('created_at'),
                            ])->filter()->max(),
                            'previousDepositDate' => Deposit::where('member_id', $member->id)
                                ->where('id', '<', $record->id)
                                ->orderBy('paid_at', 'desc')
                                ->orderBy('id', 'desc')
                                ->value('paid_at'),
                        ]);
                    }),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}
